<x-app-layout>
    <!-- Title & Header -->

    <x-slot name="title">
        {{ $friend->username }}
    </x-slot>

    <x-slot name="back_btn"></x-slot>

    <x-slot name="header_image">
        <div class="profile-img-md-container">
            <img src="{{ $friend->getProfileImageUrlAttribute() }}" alt="Profile image" class="profile-img">
        </div>
    </x-slot>

    <x-slot name="header_title">
        {{ $friend->username }}
    </x-slot>

    <x-slot name="header_buttons">
        <x-primary-button icon="fa-solid fa-receipt icon" :href="route('expenses.create', ['friend' => $friend->id])">{{ __('Add Expense') }}</x-primary-button>
        <x-primary-button icon="fa-solid fa-scale-balanced icon" :href="route('payments.create', ['friend' => $friend->id])">{{ __('Settle Up') }}</x-primary-button>
    </x-slot>

    <x-slot name="mobile_overflow_options">
        <a class="dropdown-item" href="{{ route('expenses.create', ['friend' => $friend->id]) }}">
            <i class="fa-solid fa-receipt"></i>
            <div>{{ __('Add Expense') }}</div>
        </a>
        <a class="dropdown-item" href="{{ route('payments.create', ['friend' => $friend->id]) }}">
            <i class="fa-solid fa-scale-balanced"></i>
            <div>{{ __('Settle Up') }}</div>
        </a>
    </x-slot>

    <!-- Content -->

    <div class="metrics-container margin-bottom-lg">
        @if ($overall_balance > 0)
            <div class="metric-container text-success">
                <span class="text-small">{{ __('Overall, ') }}<span class="bold-username">{{ $friend->username }}</span>{{ __(' owes you') }}</span>
                <span class="metric-number">{{ __('$') . number_format($overall_balance, 2) }}</span>
            </div>
        @elseif ($overall_balance < 0)
            <div class="metric-container text-warning">
                <span class="text-small">{{ __('Overall, you owe ') }}<span class="bold-username">{{ $friend->username }}</span></span>
                <span class="metric-number">{{ __('$') . number_format(abs($overall_balance), 2) }}</span>
            </div>
        @elseif ($friend->is_settled_up)
            <div class="metric-container text-success">
                <span class="text-small">{{ __('You and ') }}<span class="bold-username">{{ $friend->username }}</span>{{ __(' are') }}</span>
                <span class="metric-number">{{ __('Settled Up!') }}</span>
            </div>
        @else
            <div class="metric-container text-success">
                <span class="text-small">{{ __('Overall, you owe ') }}<span class="bold-username">{{ $friend->username }}</span></span>
                <span class="metric-number">{{ __('$0.00') }}</span>
            </div>
        @endif

        @foreach ($group_balances as $group_balance)
            @if ($group_balance->balance > 0)
                <div class="metric-container">
                    <a href="{{ route('groups.show', $group_balance->group_id) }}" class="info-chip info-chip-truncate info-chip-link info-chip-grey">{{ $group_balance->name }}</a>
                    <span class="text-primary text-small"><span class="bold-username">{{ $friend->username }}</span>{{ __(' owes you') }}</span>
                    <span class="text-success metric-number">{{ __('$') . number_format($group_balance->balance, 2) }}</span>
                </div>
            @elseif ($group_balance->balance < 0)
                <div class="metric-container">
                    <a href="{{ route('groups.show', $group_balance->group_id) }}" class="info-chip info-chip-truncate info-chip-link info-chip-grey">{{ $group_balance->name }}</a>
                    <span class="text-primary text-small">{{ __('You owe ') }}<span class="bold-username">{{ $friend->username }}</span></span>
                    <span class="text-warning metric-number">{{ __('$') . number_format(abs($group_balance->balance), 2) }}</span>
                </div>
            @else
                <div class="metric-container">
                    <a href="{{ route('groups.show', $group_balance->group_id) }}" class="info-chip info-chip-truncate info-chip-link info-chip-grey">{{ $group_balance->name }}</a>
                    <span class="text-primary text-small">{{ __('You and ') }}<span class="bold-username">{{ $friend->username }}</span>{{ __(' are') }}</span>
                    <span class="text-success metric-number">{{ __('Settled Up!') }}</span>
                </div>
            @endif
        @endforeach
    </div>

    <div class="section-search">
        <div class="restrict-max-width">
            <x-searchbar-secondary placeholder="{{ __('Search Friend Expenses') }}" id="search-friend-expenses"></x-searchbar-secondary>
        </div>
    </div>

    <div class="expenses-list-container">
        <!-- No expenses message -->
        <div class="notifications-empty-container hidden" id="no-friend-expenses">
            <div class="notifications-empty-icon"><i class="fa-solid fa-receipt"></i></div>
            <div class="notifications-empty-text">{{ __('No expenses!') }}</div>
        </div>

        <div class="expenses" id="friend-expenses-list"></div>

        <!-- Loading animation -->
        <div id="friend-expenses-loading">
            <x-list-loading></x-list-loading>
        </div>
    </div>
</x-app-layout>

<script>
    let page = 1;
    let loading = false;
    let lastPage = false;
    let query = '';

    function fetchExpenses(query, replace = false) {
        const loadingPlaceholder = document.getElementById('friend-expenses-loading');
        const expensesList = document.getElementById('friend-expenses-list');
        const noExpensesMessage = document.getElementById('no-friend-expenses');

        loading = true;

        if (replace) {
            expensesList.innerHTML = '';
            lastPage = false;
            page = 1;
        }

        if (lastPage) {
            loading = false;
            return;
        }

        noExpensesMessage.classList.add('hidden');
        loadingPlaceholder.classList.remove('hidden');

        $.ajax({
            url: '{{ route('friends.get-friend-expenses', $friend->id) }}' + '?page=' + page,
            method: 'GET',
            data: {
                'query': query
            },
            success: function(response) {
                if (response.is_last_page) lastPage = true;
                page = parseInt(response.current_page) + 1;

                const html = response.html;

                setTimeout(() => {
                    loadingPlaceholder.classList.add('hidden');

                    if (replace) { // Replace the content on search or page load
                        if (html.trim().length == 0) {
                            expensesList.innerHTML = '';
                            noExpensesMessage.classList.remove('hidden');
                        } else {
                            noExpensesMessage.classList.add('hidden');
                            expensesList.innerHTML = html;
                        }
                    } else { // Append to the content on scroll
                        expensesList.insertAdjacentHTML('beforeend', html); 
                    }
                }, replace ? 300 : 600);

                loading = false;
            },
            error: function(error) {
                loadingPlaceholder.classList.add('hidden');
                loading = false;
                console.error(error);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        fetchExpenses(query, true);

        const searchInput = document.getElementById("search-friend-expenses");
        searchInput.addEventListener('input', function() {
            query = searchInput.value.trim();
            fetchExpenses(query, true);
        });

        function handleScroll() {
            if (loading) return;

            if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 100) {
                fetchExpenses(query);
            }
        }
        document.addEventListener('scroll', handleScroll);
    });
</script>
