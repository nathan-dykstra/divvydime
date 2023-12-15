<x-app-layout>
    <x-slot name="header">
        <h2>{{ __('Activity') }}</h2>
    </x-slot>

    <div class="section-search">
        <div class="restrict-max-width">
            <x-searchbar-secondary placeholder="Search Activity" id="search-activity"></x-searchbar-secondary>
        </div>
    </div>

    <div class="activity-list-container">
        @include('activity.partials.notifications')
    </div>
</x-app-layout>

<style>

</style>

<script>
    activitySearchbar = document.getElementById("search-activity");
    activitySearchbar.addEventListener('input', function(event) {
        var searchString = event.target.value;

        $.ajax({
            url: "{{ route('activity.search') }}",
            method: 'POST',
            data: {
                '_token': '{{ csrf_token() }}',
                'search_string': searchString,
            },
            success: function(html) {
                notifications = $('.notifications');
                notifications.replaceWith(html);
            },
            error: function(error) {
                console.log(error);
            }
        });
    });

    function acceptFriendRequest(notificationId) {
        $.ajax({
            url: '/friends/requests/'+ notificationId + '/accept',
            method: 'POST',
            data: {
                '_token': '{{ csrf_token() }}',
            },
            success: function(response) {
                notifications = $('.notifications');

                $.ajax({
                    url: "{{ route('activity.get-updated-notifications') }}",
                    method: 'GET',
                    data: {
                        '_token': '{{ csrf_token() }}',
                    },
                    success: function(html) {
                        notifications.replaceWith(html);
                    },
                    error: function(error) {
                        console.log(error);
                    }
                });
            },
            error: function(error) {
                console.log(error);
            }
        })
    }

    function denyFriendRequest(denyBtn, notificationId) {
        $.ajax({
            url: '/friends/requests/'+ notificationId + '/deny',
            method: 'DELETE',
            data: {
                '_token': '{{ csrf_token() }}',
            },
            success: function(response) {
                notificationElement = denyBtn.closest('.notification');
                $(notificationElement).remove();
            },
            error: function(error) {
                console.log(error);
            }
        })
    }

    function deleteNotification(deleteBtn, notificationId) {
        $.ajax({
            url: '/activity/' + notificationId + '/delete',
            method: 'DELETE',
            data: {
                '_token': '{{ csrf_token() }}',
            },
            success: function(response) {
                notificationElement = deleteBtn.closest('.notification');
                $(notificationElement).remove();
            },
            error: function(error) {
                console.log(error);
            }
        })
    }
</script>