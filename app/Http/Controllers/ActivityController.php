<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\ExpenseType;
use App\Models\Group;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    const TIMEZONE = 'America/Toronto'; // TODO: make this a user setting

    /**
     * Display the user's notifications.
     */
    public function index(): View
    {
        $current_user = auth()->user();

        $notifications = Notification::select('notifications.*', 'users1.username AS sender_username', 'users2.username AS recipient_username')
            ->join('users AS users1', 'notifications.sender', 'users1.id')
            ->join('users AS users2', 'notifications.recipient', 'users2.id')
            ->where('notifications.recipient', $current_user->id)
            ->orderBy('notifications.updated_at', 'DESC')
            ->get();

        $notifications = $this->augmentNotifications($notifications);

        return view('activity.activity-list', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function delete(Request $request, $notification_id) 
    {
        Notification::where('id', $notification_id)->first()->delete();

        return response()->json([
            'message' => 'Notification deleted!',
        ]);
    }


    /**
     * Deletes all notifications except actionable notifications (friend request, group
     * invite, payment confirmation)
     */
    public function clearAll(Request $request)
    {
        $deleted_notification_ids = [];

        $notifications_to_delete = Notification::where('recipient', auth()->user()->id)
            ->whereNot(function ($query) {
                $query->where('notification_type_id', NotificationType::FRIEND_REQUEST)
                    ->whereColumn('recipient', '!=', 'creator');
            })
            ->whereNot(function ($query) {
                $query->where('notification_type_id', NotificationType::INVITED_TO_GROUP)
                    ->whereColumn('recipient', '!=', 'creator');
            })
            ->whereNot(function ($query) {
                $query->where('notification_type_id', NotificationType::PAYMENT)
                    ->whereColumn('recipient', '!=', 'creator');
            })
            ->get();

        foreach($notifications_to_delete as $notification) {
            $deleted_notification_ids[] = $notification->id;
            $notification->delete();
        }

        return response()->json([
            'message' => 'Notifications deleted!',
            'deletedNotificationIds' => $deleted_notification_ids,
        ]);
    }

    /**
     * Filters the notifications in the Activity section.
     */
    public function search(Request $request): View
    {
        $current_user = auth()->user();
        $search_string = $request->input('search_string');

        $notifications_query = Notification::select('notifications.*', 'users1.username AS sender_username')
            ->join('users AS users1', 'notifications.sender', 'users1.id')
            ->join('users AS users2', 'notifications.recipient', 'users2.id')
            ->where('notifications.recipient', $current_user->id);

        if ($search_string) {
            $notifications_query = $notifications_query->leftJoin('notification_attributes', 'notifications.id', 'notification_attributes.notification_id')
                ->leftJoin('groups', 'notification_attributes.group_id', 'groups.id')
                ->leftJoin('expenses', 'notification_attributes.expense_id', 'expenses.id')
                ->where(function ($query) use ($search_string) {
                    $query->whereRaw('users1.username LIKE ?', ["%$search_string%"])
                        ->orWhereRaw('users2.username LIKE ?', ["%$search_string%"])
                        ->orWhereRaw('groups.name LIKE ?', ["%$search_string%"])
                        ->orWhereRaw('expenses.name LIKE ?', ["%$search_string%"]);
                });
        }

        $notifications = $notifications_query->orderBy('notifications.updated_at', 'DESC')->get();

        $notifications = $this->augmentNotifications($notifications);

        return view('activity.partials.notifications', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Returns the activity.partials.notifications view.
     */
    public function getUpdatedNotifications(Request $request): View
    {
        $current_user = auth()->user();

        $notifications = Notification::select('notifications.*', 'users.username AS sender_username')
            ->join('users', 'notifications.sender', 'users.id')
            ->where('notifications.recipient', $current_user->id)
            ->orderBy('notifications.updated_at', 'DESC')
            ->get();

        $notifications = $this->augmentNotifications($notifications);

        return view('activity.partials.notifications', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Add addition information to the notifications.
     */
    protected function augmentNotifications($notifications) {
        $notifications = $notifications->map(function ($notification) {
            // Notification timestamps
            $notification->formatted_date = Carbon::parse($notification->updated_at)->diffForHumans();
            $notification->date = Carbon::parse($notification->updated_at)->format('M d, Y');
            $notification->formatted_time = Carbon::parse($notification->updated_at)->setTimezone(self::TIMEZONE)->format('g:i a');

            // Notification Group
            if ($notification->attributes?->group_id) {
                $notification->group = Group::find($notification->attributes->group_id);
            }

            // Notification Expense
            if ($notification->attributes?->expense_id) {
                $notification->expense = Expense::find($notification->attributes->expense_id);

                // Get the amount the current user lent/borrowed in the expense
                $current_user_share = ExpenseParticipant::where('expense_id', $notification->expense->id)
                    ->where('user_id', auth()->user()->id)
                    ->value('share');
                if ($notification->expense->payer === auth()->user()->id) {
                    $notification->amount_lent = number_format($notification->expense->amount - $current_user_share, 2);
                }
                if ($current_user_share) {
                    $notification->amount_borrowed = number_format($current_user_share, 2);
                }

                $notification->group = Group::find($notification->expense->groups->first()->id);

                // Additional information if expense is a Reimbursement or Payment

                $notification->is_reimbursement = $notification->expense->expense_type_id === ExpenseType::REIMBURSEMENT;

                if ($notification->expense->expense_type_id === ExpenseType::PAYMENT || $notification->expense->expense_type_id === ExpenseType::SETTLE_ALL_BALANCES) {
                    $notification->payee = $notification->expense->participants()->first();
                    $notification->is_settle_all_balances = $notification->expense->expense_type_id === ExpenseType::SETTLE_ALL_BALANCES;
                }
            }

            return $notification;
        });

        return $notifications;
    }
}
