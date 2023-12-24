<a>
    @if ($notification->creator === $notification->recipient) <!-- You left the group -->
        <div class="notification-grid">
            <div class="notification-content">
                <div>
                    <div>You left <span class="notification-username">{{ $notification->group->name }}</span>.</div>
                    <div class="text-shy">{{ $notification->formatted_date }}, {{ $notification->formatted_time }}</div>
                </div>
            </div>

            <div class="delete-notification-btn-container">
                <div class="tooltip tooltip-left">
                    <i class="fa-solid fa-trash-can delete-notification-btn" onclick="deleteNotification($(this), {{ $notification->id }})"></i>
                    <span class="tooltip-text">{{ __('Delete Notification') }}</span>
                </div>
            </div>
        </div>
    @else <!-- Other member left the group -->
        <div class="notification-grid">
            <div class="notification-content">
                <div>
                    <div><span class="notification-username">{{ $notification->username }}</span> left <span class="notification-username">{{ $notification->group->name }}</span>.</div>
                    <div class="text-shy">{{ $notification->formatted_date }}, {{ $notification->formatted_time }}</div>
                </div>
            </div>

            <div class="delete-notification-btn-container">
                <div class="tooltip tooltip-left">
                    <i class="fa-solid fa-trash-can delete-notification-btn" onclick="deleteNotification($(this), {{ $notification->id }})"></i>
                    <span class="tooltip-text">{{ __('Delete Notification') }}</span>
                </div>
            </div>
        </div>
    @endif
</a>