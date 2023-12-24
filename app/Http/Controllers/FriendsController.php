<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\Invite;
use App\Models\Notification as ModelsNotification;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\InviteNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FriendsController extends Controller
{
    /**
     * Display the user's friends.
     */
    public function index(): View
    {
        $current_user = auth()->user();

        $friends = $current_user->friends()->orderBy('username', 'asc')->get();

        return view('friends.friends-list', [
            'friends' => $friends,
        ]);
    }

    /**
     * Displays a friend's profile.
     */
    public function show($friend_id): View
    {
        $current_user = auth()->user();

        $friend = User::where('id', $friend_id)->first();

        if ($friend === null) {
            return view('friends.does-not-exist');
        } else if (!in_array($current_user->id, $friend->friends()->pluck('id')->toArray())) {
            return view('friends.not-allowed');
        }

        return view('friends.friend-profile', [
            'friend' => $friend,
        ]); 
    }

    /**
     * Send a friend request.
     */
    public function invite(Request $request): RedirectResponse
    {
        $request->validateWithBag('friendInvite', [
            'friend_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $inviter = $request->user();

        $existing_user = User::where('email', $request->input('friend_email'))->first();

        if ($existing_user) {
            // Check if the users are already friends or if a pending request already exists

            $self_request = $existing_user->id === $inviter->id;

            if ($self_request) {
                return Redirect::route('friends')->with('status', 'self-request');
            }

            $existing_friend = in_array($existing_user->id, $inviter->friends()->pluck('users.id')->toArray());

            if ($existing_friend) {
                return Redirect::route('friends')->with('status', 'existing-friend');
            }

            $existing_friend_request = ModelsNotification::where('notification_type_id', NotificationType::FRIEND_REQUEST)
                ->where('creator', $inviter->id)
                ->exists();

            if ($existing_friend_request) {
                return Redirect::route('friends')->with('status', 'existing-request');
            }

            $pending_friend_request = ModelsNotification::where('notification_type_id', NotificationType::FRIEND_REQUEST)
                ->where('creator', $existing_user->id)
                ->exists();

            if ($pending_friend_request) {
                return Redirect::route('friends')->with('status', 'pending-request');
            }

            // Create Friend Request notifications for both parties

            $friend_request_sender = ModelsNotification::create([
                'notification_type_id' => NotificationType::FRIEND_REQUEST,
                'creator' => $inviter->id,
                'sender' => $existing_user->id,
                'recipient' => $inviter->id,
            ]);

            $friend_request_recipient = ModelsNotification::create([
                'notification_type_id' => NotificationType::FRIEND_REQUEST,
                'creator' => $inviter->id,
                'sender' => $inviter->id,
                'recipient' => $existing_user->id,
            ]);
        } else {
            do {
                $token = Str::random(20);
            } while (Invite::where('token', $token)->first());
    
            Invite::create([
                'token' => $token,
                'email' => $request->input('friend_email'),
                'inviter' => $inviter->id,
            ]);
    
            $url = URL::temporarySignedRoute(
                'register.frominvite', now()->addMinutes(300), ['token' => $token]
            );
    
            Notification::route('mail', $request->input('friend_email'))->notify(new InviteNotification($url, $inviter->username));
        }

        return Redirect::route('friends')->with('status', 'invite-sent');
    }

    /**
     * Accept a friend request.
     */
    public function accept(Request $request, $notification_id)
    {
        $recipient_notification = ModelsNotification::where('id', $notification_id)->first();

        $user1_id = $recipient_notification->sender;
        $user2_id = $recipient_notification->recipient;

        $friends = Friend::create([
            'user1_id' => $user1_id,
            'user2_id' => $user2_id,
        ]);

        $sender_notification = ModelsNotification::where('notification_type_id', NotificationType::FRIEND_REQUEST)
            ->where('sender', $user2_id)
            ->where('recipient', $user1_id)
            ->first();

        $recipient_notification_update = $recipient_notification->update([
            'notification_type_id' => NotificationType::FRIEND_REQUEST_ACCEPTED,
        ]);

        $sender_notification_update = $sender_notification->update([
            'notification_type_id' => NotificationType::FRIEND_REQUEST_ACCEPTED,
        ]);

        if ($friends && $recipient_notification_update && $sender_notification_update) {
            return response()->json([
                'message' => 'Friend request accepted!',
            ]);
        } else {
            return response()->json([
                'message' => 'Error occured!',
            ], 500);
        }
    }

    /**
     * Deny a friend request.
     */
    public function deny(Request $request, $notification_id)
    {
        $recipient_notification = ModelsNotification::where('id', $notification_id)->first();

        $user1_id = $recipient_notification->sender;
        $user2_id = $recipient_notification->recipient;

        $sender_notification = ModelsNotification::where('notification_type_id', NotificationType::FRIEND_REQUEST)
            ->where('sender', $user2_id)
            ->where('recipient', $user1_id)
            ->first();

        $recipient_notification->delete();
        $sender_notification->delete();

        return response()->json([
            'message' => 'Friend request denied!',
        ]);
    }

    /**
     * Filters the friends list in the Friends section.
     */
    public function search(Request $request): View
    {
        $current_user = auth()->user();
        $search_string = $request->input('search_string');

        $friend_ids = Friend::select('user2_id AS friend_id')
            ->where('user1_id', $current_user->id)
            ->union(
                Friend::select('user1_id AS friend_id')
                    ->where('user2_id', $current_user->id)
            )
            ->get()->toArray();

        $friends = User::whereIn('id', $friend_ids)
            ->where(function ($query) use ($search_string) {
                $query->whereRaw('username LIKE ?', ["%$search_string%"])
                    ->orWhereRaw('email LIKE ?', ["%$search_string%"]);
                })
            ->orderBy('username', 'asc')
            ->get();

        return view('friends.partials.friends', ['friends' => $friends]);
    }
}