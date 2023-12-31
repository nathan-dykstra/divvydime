<x-app-layout>
    <x-slot name="header">
        <div class="btn-container-apart">
            <h2>{{ $friend?->username }}</h2>
            <div class="btn-container-end">
            </div>
        </div>
    </x-slot>

    @foreach ($expenses as $expense)
        @if ($expense->payer === auth()->user()->id) <!-- Current User paid for the expense -->
            <div class="expense">
                <div>
                    <div class="expense-name">
                        <h4>{{ $expense->name }}</h4>
                        <a class="expense-group" href="{{ route('groups.show', $expense->group->id) }}">{{ $expense->group->name }}</a>
                    </div>

                    <div class="expense-amount text-small">You paid ${{ $expense->amount }}</div>

                    <x-tooltip side="bottom" icon="fa-solid fa-calendar-days" tooltip="{{ $expense->date . ' at ' . $expense->formatted_time }}">
                        <div class="text-shy width-content">{{ $expense->formatted_date }}</div>
                    </x-tooltip>
                </div>

                <div class="user-amount text-success">
                    <div class="text-small">{{ __('You lent') }}</div>
                    <div class="user-amount-value">${{ $expense->lent }}</div>
                </div>
            </div>
        @else <!-- Friend paid for the expense -->
            <div class="expense">
                <div>
                    <div class="expense-name">
                        <h4 class="expense-name-text">{{ $expense->name }}</h4>
                        <a class="expense-group" href="{{ route('groups.show', $expense->group->id) }}">{{ $expense->group->name }}</a>
                    </div>

                    <div class="expense-amount text-small"><span class="notification-username">{{ $expense->payer_user->username }}</span> paid ${{ $expense->amount }}</div> 

                    <x-tooltip side="bottom" icon="fa-solid fa-calendar-days" tooltip="{{ $expense->date . ' at ' . $expense->formatted_time }}">
                        <div class="text-shy width-content">{{ $expense->formatted_date }}</div>
                    </x-tooltip>
                </div>

                <div class="user-amount text-warning">
                    <div class="text-small">{{ __('You borrowed') }}</div>
                    <div class="user-amount-value">${{ $expense->borrowed }}</div>
                </div>
            </div>
        @endif
    @endforeach
</x-app-layout>
