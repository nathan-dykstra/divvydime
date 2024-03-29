<?php

namespace App\Http\Middleware;

use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpenseAuthorization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $current_user = $request->user();

        if ($request->route('expense_id') || $request->route('expense')) {
            $expense = null;

            if ($request->route('expense_id')) {
                $expense = Expense::find($request->route('expense_id'));
            } else {
                $expense = $request->route('expense');
            }

            if ($expense === null) {
                abort(404, "Uh oh! This expense doesn't exist.");
            } else if ($expense->group_id === Group::DEFAULT_GROUP) {
                if (!in_array($current_user->id, $expense->involvedUsers()->pluck('id')->toArray())) {
                    abort(403, "Uh oh! You're not involved in this expense.");
                }
            } else {
                $expense_group = $expense->group()->first();
                if (!in_array($current_user->id, $expense_group->members()->pluck('users.id')->toArray())) {
                    abort(403, "Uh oh! You're not involved in this expense.");
                }
            }
        }
        
        if ($request->input('group')) {
            $group = Group::find($request->input('group'));

            if (!in_array($current_user->id, $group->members()->pluck('users.id')->toArray())) {
                abort(403, "Uh oh! You can't create an expense with that group.");
            }
        }

        if ($request->input('friend')) {
            $friend = User::find($request->input('friend'));

            if (!in_array($current_user->id, $friend?->friends()->pluck('users.id')->toArray() ?? [])) {
                abort(403, "Uh oh! You can't create an expense with that user.");
            }
        }

        return $next($request);
    }
}
