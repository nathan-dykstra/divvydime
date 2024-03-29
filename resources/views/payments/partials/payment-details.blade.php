<div class="container margin-bottom-lg">
    <x-validation-warning id="payee-validation-warning">{{ __('Select a payee!') }}</x-validation-warning>
    <x-validation-warning id="balance-validation-warning">{{ __('Select a balance!') }}</x-validation-warning>

    <div class="restrict-max-width">
        <form method="post" action="{{ route('payments.store') }}">
            @csrf

            <div class="payment-choose-user space-bottom-lg {{ $friend ? 'hidden' : '' }}" id="payment-choose-user">
                <h4 class="margin-bottom-sm">{{ __('Who did you pay?') }}</h4>

                <ul id="payment-users-list">
                    @foreach ($users_selection as $user)
                        <li>
                            <label class="payment-user-selector-item" for="choose-user-item-{{ $user->id }}" data-user-id="{{ $user->id }}" data-username="{{ $user->username }}" onclick="setPaymentUser(this)">
                                <div class="payment-user-selector-radio">
                                    <input type="radio" id="choose-user-item-{{ $user->id }}" class="radio" name="payment-payee" value="{{ $user->id }}" {{ $expense?->payer === $user->id || $friend?->id == $user->id ? 'checked' : '' }}/>
                                    <div class="user-photo-name">
                                        <div class="profile-circle-sm-placeholder"></div>
                                        <div class="split-equal-item-name">{{ $user->username }}</div>
                                    </div>
                                </div>

                                <div class="payment-user-amount">
                                    @if ($user->total_balance > 0)
                                        <div class="text-small text-success">{{ __('You are owed $') . number_format(abs($user->total_balance), 2) }}</div>
                                    @elseif ($user->total_balance == 0)
                                        <div class="text-shy">{{ __('Settled up') }}</div>
                                    @else
                                        <div class="text-small text-warning">{{ __('You owe $') . number_format(abs($user->total_balance), 2) }}</div>
                                    @endif
                                </div>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <div class="btn-container-start">
                    <x-primary-button onclick="showBalanceSelector()">{{ __('Next') }}</x-primary-button>
                </div>
            </div>

            <div class="payment-choose-balance space-bottom-lg {{ $group || !$friend ? 'hidden' : '' }}" id="payment-choose-balance">
                <h4 class="margin-bottom-sm">{{ __('Choose a balance to settle') }}</h4>

                <ul id="payment-balances-list">
                    <li>
                        <label class="payment-group-selector-item" for="choose-balance-item-all" onclick="setPaymentBalance(this)">
                            <div class="payment-user-selector-radio">
                                <input type="radio" id="choose-balance-item-all" class="radio" name="payment-balance" value="-1" {{ $expense === null ? 'checked' : '' }}/>
                                <div class="user-photo-name">
                                    <div class="profile-circle-sm-placeholder"></div>
                                    <div class="split-equal-item-name">All balances</div>
                                </div>
                            </div>
                        </label>
                    </li>

                    @foreach ($balances_selection as $balance)
                        <li>
                            @if ($balance->balance < 0) <!-- Current users owes money in this group -->
                                <label class="payment-group-selector-item" for="choose-balance-item-{{ $balance->id }}" data-user-id="{{ $balance->friend }}" data-group-name="{{ $balance->group_name }}" data-balance="{{ number_format(abs($balance->balance), 2) }}" onclick="setPaymentBalance(this)">
                                    <div class="payment-user-selector-radio">
                                        <input type="radio" id="choose-balance-item-{{ $balance->id }}" class="radio" name="payment-balance" value="{{ $balance->id }}" {{ $expense?->group_id === $balance->group_id || $group?->id == $balance->group_id ? 'checked' : '' }}/>
                                        <div class="user-photo-name">
                                            <div class="profile-circle-sm-placeholder"></div>
                                            <div class="split-equal-item-name">{{ $balance->group_name }}</div>
                                        </div>
                                    </div>

                                    <div class="payment-user-amount">
                                        <div class="text-small text-warning">{{ __('You owe $') . number_format(abs($balance->balance), 2) }}</div>
                                    </div>
                                </label>
                            @else <!-- Current use does not owe money in this group (either they are owed or are settled up) -->
                                <label class="payment-group-selector-item-disabled" for="choose-balance-item-{{ $balance->id }}" data-user-id="{{ $balance->friend }}">
                                    <div class="payment-user-selector-radio">
                                        <input type="radio" id="choose-balance-item-{{ $balance->id }}" class="radio" name="payment-balance" disabled/>
                                        <div class="user-photo-name">
                                            <div class="profile-circle-sm-placeholder"></div>
                                            <div class="split-equal-item-name">{{ $balance->group_name }}</div>
                                        </div>
                                    </div>

                                    <div class="payment-user-amount">
                                        @if ($balance->balance > 0)
                                            <div class="text-small text-success">{{ __('You are owed $') . number_format(abs($balance->balance), 2) }}</div>
                                        @else
                                            <div class="text-shy">{{ __('Settled up') }}</div>
                                        @endif
                                    </div>
                                </label>
                            @endif
                        </li>
                    @endforeach
                </ul>


                <div class="btn-container-start">
                    <x-primary-button onclick="showPayeeSelector()">{{ __('Back') }}</x-primary-button>
                    <x-primary-button onclick="showPaymentForm()">{{ __('Next') }}</x-primary-button>
                </div>
            </div>

            <div class="payment-form {{ $group && $friend ? '' : 'hidden' }} space-top-lg" id="payment-form">
                <div>
                    <div class="payment-user-photos-container">
                        <div class="payment-user"> <!-- TODO: show current user's profile photo -->
                            <div class="profile-circle-lg-placeholder"></div>
                        </div>
    
                        <i class="payment-arrow fa-solid fa-right-long fa-2xl"></i>
    
                        <div class="payment-user">
                            <div class="profile-circle-lg-placeholder"></div>
                        </div>
                    </div>
    
                    <div class="expense-paid-split">
                        {{ __('You paid') }}
    
                        <x-primary-button class="expense-round-btn" id="payment-select-recipient" onclick="showPayeeSelector()">
                            <div class="expense-round-btn-text">
                                {{ $friend ? $friend->username : __('Choose Recipient') }}
                            </div>
                        </x-primary-button>
                    </div>
                </div>

                <div class="payment-amount-container">
                    <div class="expense-input-container payment-amount">
                        <span class="expense-currency">{{ __('$') }}</span><input id="payment-amount" class="expense-form-amount" name="payment-amount" type="number" step="0.01" min="0" max="99999999" placeholder="{{ __('0.00') }}" value="{{ old('payment-amount', $expense ? $expense->amount : '') }}" autocomplete="off" required />
                    </div>
                </div>

                <div class="expense-group-date-media-container">
                    <div>
                        <div class="expense-group-date-media">
                            <x-primary-button class="expense-round-btn expense-round-btn-equal-width" id="payment-group-btn" onclick="showBalanceSelector()">
                                <div class="expense-round-btn-text">
                                    @if ($expense === null) <!-- Creating a new Payment -->
                                        @if ($group) <!-- Payment was added from a Group, so show this Group by default -->
                                            {{ $group->name }}
                                        @else <!-- Payment was not added from a Group (or it was added from "Individual Expenses") -->
                                            {{ $default_group->name }}
                                        @endif
                                    @else <!-- Updating an existing Payment -->
                                        {{ $expense->group()->first()->name }}
                                    @endif
                                </div>
                            </x-primary-button>
                        </div>
                    </div>

                    <div>
                        <div class="expense-group-date-media">
                            <x-primary-button class="expense-round-btn expense-round-btn-equal-width" id="payment-date-btn" onclick="toggleDateDropdown()">
                                <div class="expense-round-btn-text">
                                    {{ $formatted_today }}
                                </div>
                            </x-primary-button>
                        </div>

                        <div class="expense-expand-dropdown" id="payment-date-dropdown">
                            <h4 class="margin-bottom-sm">{{ __('When did you send the payment?') }}</h4>

                            <div class="expense-datepicker-container">
                                <!-- Flowbite Tailwind CSS Datepicker -->
                                <div id="flowbite-datepicker" inline-datepicker datepicker-buttons datepicker-format="yyyy-mm-dd" data-date="{{ $today }}"></div>
                            </div>
                        </div>

                        <input type="hidden" id="payment-date" name="payment-date" value="{{ $today }}" />
                    </div>

                    <div>
                        <div class="expense-group-date-media">
                            <x-primary-button class="expense-round-btn expense-round-btn-equal-width" id="payment-media-btn" onclick="toggleMediaDropdown()">
                                <div class="expense-round-btn-text">
                                    {{ __('Add Note/Media') }}
                                </div>
                            </x-primary-button>
                        </div>

                        <div class="expense-expand-dropdown" id="payment-media-dropdown">
                            <h4 class="margin-bottom-sm">{{ __('Add a note or an image') }}</h4>

                            <x-input-label for="payment-note" :value="__('Note')" />
                            <x-text-area id="payment-note" name="payment-note" maxlength="65535" :value="old('payment-note', $payment->note ?? '')" />
                        </div>
                    </div>
                </div>

                <div class="btn-container-start">
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .payment-user-photos-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1em;
        margin-bottom: 1em;
    }

    .payment-user {
        border-radius: var(--border-radius);
    }

    .payment-amount-container {
        padding-bottom: 2em;
        border-bottom: 1px solid var(--border-grey);
        display: flex;
        justify-content: center;
    }

    .payment-amount {
        width: 250px;
    }

    .payment-select-user {

    }

    .payment-user-amount {

    }

    .payment-arrow {
        color: var(--text-shy);
    }
</style>

<script>
    const paymentChooseUser = document.getElementById('payment-choose-user');
    const paymentChooseBalance = document.getElementById('payment-choose-balance');
    const paymentForm = document.getElementById('payment-form');

    const dateDropdown = document.getElementById('payment-date-dropdown');
    const mediaDropdown = document.getElementById('payment-media-dropdown');

    const datePicker = document.getElementById('flowbite-datepicker');

    const currentAmountInput = document.getElementById('payment-amount');
    const currentDateInput = document.getElementById('payment-date');
    const currentNoteInput = document.getElementById('payment-note');

    const userBtn = document.getElementById('payment-select-recipient');
    const dateBtn = document.getElementById('payment-date-btn');
    const mediaBtn = document.getElementById('payment-media-btn');
    const groupBtn = document.getElementById('payment-group-btn')

    const payeesList = document.getElementById('payment-users-list');
    const balancesList = document.getElementById('payment-balances-list');

    function validateRadio(radioBtns) {
        let isChecked = Array.from(radioBtns).some(button => button.checked);
        return isChecked;
    }

    function showPayeeSelector() {
        hideAllValidationWarnings();

        paymentChooseUser.classList.remove('hidden');

        paymentChooseBalance.classList.add('hidden');
        paymentForm.classList.add('hidden');
    }

    function showBalanceSelector() {
        let payeeRadioBtns = document.getElementsByName('payment-payee');
        let payeeValidationWarning = document.getElementById('payee-validation-warning');

        if (validateRadio(payeeRadioBtns)) {
            hideAllValidationWarnings();

            paymentChooseBalance.classList.remove('hidden');

            paymentChooseUser.classList.add('hidden');
            paymentForm.classList.add('hidden');
        } else {
            showValidationWarning(payeeValidationWarning);
        }
    }

    function showPaymentForm() {
        let balanceRadioBtns = document.getElementsByName('payment-balance');
        let balanceValidationWarning = document.getElementById('balance-validation-warning');

        if (validateRadio(balanceRadioBtns)) {
            hideAllValidationWarnings();

            paymentForm.classList.remove('hidden');

            paymentChooseUser.classList.add('hidden');
            paymentChooseBalance.classList.add('hidden');
        } else {
            showValidationWarning(balanceValidationWarning);
        }
    }

    function setPaymentUser(userItem) {
        // TODO: Update payee profile photo

        let payeeUsername = userItem.dataset.username;

        userBtn.querySelector('.expense-round-btn-text').textContent = payeeUsername;
        updateBalanceOptions(userItem.dataset.userId);
    }

    function setPaymentBalance(balanceItem) {
        let groupName = balanceItem.dataset.groupName;
        let balance = balanceItem.dataset.balance;

        groupBtn.querySelector('.expense-round-btn-text').textContent = groupName;
        currentAmountInput.value = balance;
    }

    function updateBalanceOptions(userId) {
        let balanceItems = balancesList.querySelectorAll('.payment-group-selector-item, .payment-group-selector-item-disabled');

        balanceItems.forEach(function(item) {
            if (item.dataset.userId === userId) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
                item.querySelector('input[name="payment-balance"]').checked = false;
            }
        })
    }

    function toggleMediaDropdown() {
        dateDropdown.classList.remove('expense-expand-dropdown-open');

        mediaDropdown.classList.toggle('expense-expand-dropdown-open');
    }

    function toggleDateDropdown() {
        mediaDropdown.classList.remove('expense-expand-dropdown-open');

        dateDropdown.classList.toggle('expense-expand-dropdown-open');
    }

    datePicker.addEventListener('changeDate', function(event) {
        // Get selected date in 'yyyy-mm-dd' format
        let selectedDate = new Date(event.detail.date);

        const inputDate = selectedDate.toISOString().split('T')[0];

        let formattedDateOptions = { month: 'long', day: 'numeric', year: 'numeric' };
        const formattedDate = selectedDate.toLocaleDateString(undefined, formattedDateOptions);

        currentDateInput.value = inputDate;
        $(dateBtn).children('.expense-round-btn-text').text(formattedDate);
    })

    document.addEventListener('DOMContentLoaded', function() {
        // Resize the "Note" textarea to fit it's content
        resizeTextarea(currentNoteInput);
    })
</script>
