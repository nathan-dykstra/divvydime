<?php

namespace App\Models;

use App\Events\UserDeleting;
use App\Traits\DefaultImage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    use DefaultImage;

    const DEFAULT_USER = 1;
    const PROFILE_IMAGE_PATH = 'images/profile/';

    /**
     * Defines the User to UserPreference relationship.
     */
    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Defines the User to Group relationship.
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id');
    }

    /**
     * Returns the user's friends.
     */
    public function friends()
    {
        $friend_ids = Friend::select('user2_id AS friend_id')
            ->where('user1_id', $this->id)
            ->union(
                Friend::select('user1_id AS friend_id')
                    ->where('user2_id', $this->id)
            )
            ->get()->toArray();

        $friends = User::whereIn('users.id', $friend_ids);

        return $friends;
    }

    /**
     * Returns all expenses the user is involved in.
     */
    public function expenses()
    {
        $user_id = $this->id;

        $user_groups = Group::select('id')->whereHas('members', function ($query) use ($user_id) {
                $query->where('users.id', $user_id);
            })
            ->whereNot('id', Group::DEFAULT_GROUP)
            ->get()->toArray();

        $expeneses = Expense::select('expenses.*')
            ->where(function ($query) use ($user_id, $user_groups) {
                $query->where('payer', $user_id)
                    ->orWhereHas('participants', function ($query) use ($user_id) {
                        $query->where('users.id', $user_id);
                    })
                    ->orWhereHas('groups', function ($query) use ($user_groups) {
                        $query->whereIn('group_id', $user_groups);
                    });
            })
            ->distinct();

        return $expeneses;
    }

    /**
     * Returns the URL to the user's profile image.
     */
    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_img_file === null) {
            return null;
        }

        return asset(self::PROFILE_IMAGE_PATH . $this->profile_img_file);
    }

    /**
     * Creates a default profile image for the user with their first initial.
     */
    public function createDefaultProfileImage()
    {
        $filename = time().'-profile-image-' . $this->id . '.png';

        $asset = $this->createDefaultImage(self::PROFILE_IMAGE_PATH, $filename, $this->username);

        // Save the filename in the database
        $this->profile_img_file = $filename;
        $this->save();

        return $asset;
    }

    /**
     * Deletes the profile image from the server.
     */
    public function deleteProfileImage()
    {
        $image_path = public_path(self::PROFILE_IMAGE_PATH . $this->profile_img_file);

        // Delete the image
        if ($this->profile_img_file && file_exists($image_path)) {
            unlink($image_path);
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'profile_img_file',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $dispatchesEvents = [
        'deleting' => UserDeleting::class,
    ];
}
