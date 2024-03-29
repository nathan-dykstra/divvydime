<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateGroupRequest;
use App\Models\Balance;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\ExpenseType;
use App\Models\Friend;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\Notification as ModelsNotification;
use App\Models\NotificationAttribute;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\GroupInviteNotification;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GroupsController extends Controller
{
    const TIMEZONE = 'America/Toronto'; // TODO: make this a user setting
    const GROUP_BALANCES_SHOWN = 3;
    const GROUP_BLANACES_LIMITED = 2;

    /**
     * Displays the User's Groups.
     */
    public function index(): View
    {
        $current_user = auth()->user();

        $groups = $current_user->groups()
            ->orderByRaw("
                CASE
                    WHEN groups.id = ? THEN 0
                    ELSE 1
                END, groups.name ASC
            ", [Group::DEFAULT_GROUP])
            ->get();

        $groups = $this->augmentGroups($groups);

        return view('groups.groups-list', [
            'groups' => $groups,
        ]);
    }

    /**
     * Displays the create Group form.
     */
    public function create(): View
    {
        return view('groups.create', ['group' => null]);
    }

    /**
     * Saves the new Group.
     */
    public function store(CreateGroupRequest $request): RedirectResponse
    {
        $current_user = auth()->user();

        $group = Group::create($request->validated());

        $group->owner = $current_user->id;
        $group->save();

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $current_user->id,
        ]);

        return Redirect::route('groups.show', $group->id)->with('status', 'group-created');
    }

    /**
     * Displays the Group.
     */
    public function show($group_id): View
    {
        $current_user = auth()->user();

        $group = Group::where('id', $group_id)->first();
        $group->is_default = $group->id === Group::DEFAULT_GROUP;

        $expenses = $group->expenses();

        if ($group->id === Group::DEFAULT_GROUP) {
            $expenses = $expenses->where(function ($query) use ($current_user) {
                $query->where('expenses.payer', $current_user->id)
                    ->orWhereHas('participants', function ($query) use ($current_user) {
                        $query->where('users.id', $current_user->id);
                    });
            });
        }

        $expenses = $expenses->orderBy('date', 'DESC')->get();

        $expenses = $expenses->map(function ($expense) use ($current_user) {
            $expense->payer_user = User::where('id', $expense->payer)->first();

            $expense->formatted_date = Carbon::parse($expense->date)->diffForHumans();

            $expense->date = Carbon::parse($expense->date)->format('M d, Y');

            $current_user_share = ExpenseParticipant::where('expense_id', $expense->id)
                ->where('user_id', $current_user->id)
                ->value('share');

            if ($expense->payer === $current_user->id) {
                $expense->lent = number_format($expense->amount - $current_user_share, 2);
            }
            if ($current_user_share) {
                $expense->borrowed = number_format($current_user_share, 2);
            }
            $expense->amount = number_format($expense->amount, 2);

            $expense->group = Group::where('id', $expense->group_id)->first();

            $expense->is_reimbursement = $expense->expense_type_id === ExpenseType::REIMBURSEMENT;

            $expense->is_payment = $expense->expense_type_id === ExpenseType::PAYMENT;
            $expense->payee = $expense->is_payment ? $expense->participants()->first() : null;

            return $expense;
        });

        $overall_balance = Balance::where('group_id', $group->id)
            ->where('user_id', $current_user->id)
            ->sum('balance');

        $group_balances_count = Balance::where('balances.group_id', $group->id)
            ->where('balances.user_id', $current_user->id)
            ->count();

        $group_balances_shown_limit = static::GROUP_BALANCES_SHOWN;

        if ($group_balances_count > static::GROUP_BALANCES_SHOWN) {
            $group_balances_shown_limit = static::GROUP_BLANACES_LIMITED;
        }

        $individual_balances = Balance::join('users', 'balances.friend', 'users.id')
            ->select('balances.balance', 'users.username')
            ->where('balances.group_id', $group->id)
            ->where('balances.user_id', $current_user->id)
            ->whereNot('balances.balance', 0)
            ->limit($group_balances_shown_limit)
            ->orderBy('users.username', 'ASC')
            ->get();

        $additional_balances_count = $group_balances_count - $group_balances_shown_limit;

        return view('groups.show', [
            'group' => $group,
            'expenses' => $expenses,
            'overall_balance' => $overall_balance,
            'individual_balances' => $individual_balances,
            'additional_balances_count' => $additional_balances_count,
        ]);
    }

    /**
     * Displays the group settings.
     */
    public function settings(Group $group): View
    {
        $current_user = auth()->user();

        $group_members = $group->members()
            ->orderByRaw("
                CASE
                    WHEN users.id = ? THEN 0
                    ELSE 1
                END, users.username ASC
            ", [$current_user->id])
            ->get();

        $friends = $current_user->friends()->orderBy('username', 'asc')->get();

        return view('groups.group-settings', [
            'group' => $group,
            'group_members' => $group_members,
            'friends' => $friends,
        ]);
    }

    /**
     * Updates the group details.
     */
    public function update(CreateGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return Redirect::route('groups.settings', $group->id)->with('status', 'group-updated');
    }

    /**
     * Filters the friends list on the "Add Members" modal.
     */
    public function searchFriendsToInvite(Request $request, Group $group): View
    {
        $search_string = $request->input('search_string');

        $friends = auth()->user()->friends()
            ->where(function ($query) use ($search_string) {
                $query->whereRaw('users.username LIKE ?', ["%$search_string%"])
                    ->orWhereRaw('users.email LIKE ?', ["%$search_string%"]);
            })
            ->orderBy('username', 'asc')
            ->get();

        return view('groups.partials.friends-to-invite', [
            'group' => $group,
            'friends' => $friends,
        ]);
    }

    /**
     * Send an invite to a group.
     */
    public function invite(Request $request, Group $group)
    {
        $inviter = $request->user();

        $user_emails = $request->input('emails');

        // TODO: email validation fix
        $rules = [
            'email' => ['string', 'lowercase', 'email', 'max:255']
        ];

        $invite_errors = 0;

        foreach ($user_emails as $email) {
            $validator = Validator::make(['email' => $email], $rules);

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $existing_user = User::where('email', $email)->first();

            if ($existing_user) {
                // Check if the user is already in the group or if a pending invite exists.

                $self_request = $existing_user->id === $inviter->id;

                if ($self_request) {
                    $invite_errors++;
                    continue;
                }

                $existing_member = in_array($existing_user->id, $group->members()->pluck('users.id')->toArray());

                if ($existing_member) {
                    $invite_errors++;
                    continue;
                }

                $existing_group_invite = ModelsNotification::where('notification_type_id', NotificationType::INVITED_TO_GROUP)
                    ->where('creator', $inviter->id)
                    ->where('recipient', $existing_user->id)
                    ->exists();

                if ($existing_group_invite) {
                    $invite_errors++;
                    continue;
                }

                // Create Group Invite notifications for both parties

                $inviter_notification = ModelsNotification::create([
                    'notification_type_id' => NotificationType::INVITED_TO_GROUP,
                    'creator' => $inviter->id,
                    'sender' => $existing_user->id,
                    'recipient' => $inviter->id,
                ]);

                NotificationAttribute::create([
                    'notification_id' => $inviter_notification->id,
                    'group_id' => $group->id,
                ]);

                $invitee_notification = ModelsNotification::create([
                    'notification_type_id' => NotificationType::INVITED_TO_GROUP,
                    'creator' => $inviter->id,
                    'sender' => $inviter->id,
                    'recipient' => $existing_user->id,
                ]);

                NotificationAttribute::create([
                    'notification_id' => $invitee_notification->id,
                    'group_id' => $group->id,
                ]);
            } else {
                // Send an invite to app email

                do {
                    $token = Str::random(20);
                } while (GroupInvite::where('token', $token)->first());

                GroupInvite::create([
                    'token' => $token,
                    'email' => $email,
                    'group_id' => $group->id,
                ]);

                $url = URL::temporarySignedRoute(
                    'register.from-group-invite', now()->addMinutes(300), ['token' => $token]
                );

                Notification::route('mail', $email)->notify(new GroupInviteNotification($url, $inviter->username, $group->name));
            }
        }

        if ($invite_errors === 0) {
            Session::flash('status', 'invite-sent');
        } else if ($invite_errors === count($user_emails)) {
            Session::flash('status', 'invite-errors');
        } else {
            Session::flash('status', 'invite-sent-with-errors');
        }

        return response()->json([
            'message' => 'Invite sent successfully!',
            'redirect' => route('groups.settings', $group),
        ]);
    }

    /**
     * Accept a group invite.
     */
    public function accept(Request $request)
    {
        $invitee_notification_id = $request->input('notification_id');
        $invitee_notification = ModelsNotification::where('id', $invitee_notification_id)->first();

        $group_id = $request->input('group_id');

        $invitee_id = $invitee_notification->recipient;

        // Update the invitee's notification
        $invitee_notification->update([
            'notification_type_id' => NotificationType::JOINED_GROUP,
            'creator' => $invitee_id,
        ]);

        // Add invitee to group
        GroupMember::firstOrCreate([
            'group_id' => $group_id,
            'user_id' => $invitee_id,
        ]);

        return response()->json([
            'message' => 'Friend request accepted!',
        ]);
    }

    /**
     * Reject a group invite.
     */
    public function reject(Request $request)
    {
        // Delete inviter's and invitee's notifications

        $invitee_notification_id = $request->input('notification_id');
        $invitee_notification = ModelsNotification::where('id', $invitee_notification_id)->first();

        $inviter_id = $invitee_notification->sender;
        $invitee_id = $invitee_notification->recipient;

        $inviter_notification = ModelsNotification::where('notification_type_id', NotificationType::INVITED_TO_GROUP)
            ->where('sender', $invitee_id)
            ->where('recipient', $inviter_id)
            ->first();

        $invitee_notification->delete();

        if ($inviter_notification) {
            $inviter_notification->delete();
        }

        return response()->json([
            'message' => 'Friend request denied!',
        ]);
    }

    /**
     * Remove a member from the Group.
     */
    public function removeMember(Request $request, Group $group)
    {
        $member_id = $request->input('member_id');

        // TODO: Change all member's group expenses to "DivvyDime User"

        GroupMember::where('group_id', $group->id)->where('user_id', $member_id)->delete();

        Session::flash('status', 'member-removed');

        return response()->json([
            'message' => 'Member removed successfully!',
            'redirect' => route('groups.settings', $group),
        ]);
    }

    /**
     * Removes the current user from the Group.
     */
    public function leaveGroup(Request $request, Group $group)
    {
        $current_user = auth()->user();

        // TODO: Change all of the current user's group expenses to "DivvyDime User"

        if ($group->owner === $current_user->id) {
            // Group ownership needs to change
            if ($group->members()->count() > 1 ) {
                // Group owner can be assigned to another member

                $new_owner = GroupMember::where('group_id', $group->id)
                    ->whereNot('user_id', $current_user->id)
                    ->orderBy('created_at', 'asc')
                    ->pluck('user_id')
                    ->first();

                $group->owner = $new_owner;
                $group->save();

                // Send Group members a "user left group" notification
                foreach ($group->members()->pluck('users.id')->toArray() as $member_id) {
                    $left_group_notification = ModelsNotification::create([
                        'notification_type_id' => NotificationType::LEFT_GROUP,
                        'creator' => $current_user->id,
                        'sender' => $current_user->id,
                        'recipient' => $member_id,
                    ]);

                    NotificationAttribute::create([
                        'notification_id' => $left_group_notification->id,
                        'group_id' => $group->id,
                    ]);
                }

                GroupMember::where('group_id', $group->id)->where('user_id', $current_user->id)->delete();
            } else {
                // Current user is the only member so the group is deleted

                // TODO: Create group deleted notification ?

                $group->delete();
            }
        } else {
            // Send Group members a "user left group" notification
            foreach ($group->members()->pluck('users.id')->toArray() as $member_id) {
                $left_group_notification = ModelsNotification::create([
                    'notification_type_id' => NotificationType::LEFT_GROUP,
                    'creator' => $current_user->id,
                    'sender' => $current_user->id,
                    'recipient' => $member_id,
                ]);

                NotificationAttribute::create([
                    'notification_id' => $left_group_notification->id,
                    'group_id' => $group->id,
                ]);
            }

            GroupMember::where('group_id', $group->id)->where('user_id', $current_user->id)->delete();
        }

        Session::flash('status', 'left-group');

        return response()->json([
            'message' => 'Left group successfully!',
            'redirect' => route('groups'),
        ]);
    }

    /**
     * Deletes the Group.
     */
    public function destroy(Request $request, Group $group)
    {
        $group->delete();

        Session::flash('status', 'group-deleted');

        return response()->json([
            'message' => 'Group deleted successfully!',
            'redirect' => route('groups'),
        ]);
    }

    /**
     * Filters the Groups list.
     */
    public function search(Request $request): View
    {
        $current_user = auth()->user();

        $search_string = $request->input('search_string');

        $groups_query = $current_user->groups();

        if ($search_string) {
            $groups_query = $groups_query->select('groups.*')
                ->join('group_members AS gm', 'groups.id', 'gm.group_id')
                ->join('users', 'gm.user_id', 'users.id')
                ->where(function ($query) use ($search_string) {
                    $query->whereRaw('users.username LIKE ?', ["%$search_string%"])
                        ->orWhereRaw('groups.name LIKE ?', ["%$search_string%"]);
                })
                ->distinct();
        }

        $groups = $groups_query->orderByRaw("
            CASE
                WHEN groups.id = ? THEN 0
                ELSE 1
            END, groups.name ASC
        ", [Group::DEFAULT_GROUP])
        ->get();

        $groups = $this->augmentGroups($groups);

        return view('groups.partials.groups', ['groups' => $groups]);
    }

    /**
     * Add default Group information to the Groups.
     */
    protected function augmentGroups($groups)
    {
        $groups = $groups->map(function ($group) {
            $group->is_default = $group->id === Group::DEFAULT_GROUP;

            $group->overall_balance = Balance::where('group_id', $group->id)
                ->where('user_id', auth()->user()->id)
                ->sum('balance');

            return $group;
        });

        return $groups;
    }
}


