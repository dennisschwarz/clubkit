<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Members\Models\Member;
use Modules\Treasury\Http\Requests\StoreAccountRequest;
use Modules\Treasury\Http\Requests\StoreCategoryRequest;
use Modules\Treasury\Http\Requests\StoreTransactionRequest;
use Modules\Treasury\Http\Requests\UpdateAccountRequest;
use Modules\Treasury\Http\Requests\UpdateCategoryRequest;
use Modules\Treasury\Http\Requests\UpdateTransactionRequest;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Models\TreasuryCategory;
use Modules\Treasury\Models\TreasuryTaskMeta;
use Modules\Treasury\Models\TreasuryTransaction;
use Modules\Treasury\Services\TreasuryVisibilityService;

/**
 * Handles the Treasury module's main UI: overview, accounts, categories, and transactions.
 *
 * Contribution task management is handled by TreasuryContributionController.
 *
 * The view has four local tabs:
 *   zusammenfassung – summary stats with account dropdown + recent 10 transactions
 *   buchungen       – 50/50 split of income/expense transaction lists with filter
 *   konten          – account management
 *   beitraege       – contribution tracking
 *
 * All data passed to @json() in views is prepared with foreach loops.
 * Arrow functions and Collection::map() are intentionally avoided in @json() contexts.
 */
class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryVisibilityService $visibility
    ) {}

    /**
     * Renders the treasury overview with all four tabs.
     *
     * Supported query parameters:
     *   tab        string  Active local tab: 'zusammenfassung' (default) | 'buchungen' | 'konten' | 'beitraege'
     *   account    int     Filter Buchungen tab by account ID
     *   category   int     Filter Buchungen tab by category ID
     *   date_from  string  Lower bound for transaction_date (Y-m-d)
     *   date_to    string  Upper bound for transaction_date (Y-m-d)
     *   q          string  Full-text search on description
     */
    public function index(Request $request): View
    {
        $user            = $request->user();
        $visibleAccounts = $this->visibility->visibleAccounts($user);
        $visibleIds      = $visibleAccounts->pluck('id')->all();

        $categories = TreasuryCategory::orderBy('name')->get();

        // ── Global stats (all visible accounts, unfiltered) ───────────────────
        $totalIncome  = (float) TreasuryTransaction::whereIn('account_id', $visibleIds)->where('type', 'income')->sum('amount');
        $totalExpense = (float) TreasuryTransaction::whereIn('account_id', $visibleIds)->where('type', 'expense')->sum('amount');
        $totalBalance = $totalIncome - $totalExpense;

        // ── Per-account stats — single aggregated query instead of N*2 ────────
        // Groups by account_id + type, eliminating the N+1 pattern.
        $aggregates = TreasuryTransaction::selectRaw(
            'account_id, type, COALESCE(SUM(amount), 0) as total'
        )->whereIn('account_id', $visibleIds)
         ->groupBy('account_id', 'type')
         ->get()
         ->groupBy('account_id');

        $accountStats = [];
        foreach ($visibleAccounts as $a) {
            $rows = $aggregates->get($a->id, collect());
            $inc  = round((float) ($rows->firstWhere('type', 'income')?->total  ?? 0), 2);
            $exp  = round((float) ($rows->firstWhere('type', 'expense')?->total ?? 0), 2);

            $accountStats[$a->id] = [
                'id'      => $a->id,
                'name'    => $a->name,
                'income'  => $inc,
                'expense' => $exp,
                'balance' => round($inc - $exp, 2),
            ];
        }

        $globalStats = [
            'income'  => round($totalIncome, 2),
            'expense' => round($totalExpense, 2),
            'balance' => round($totalBalance, 2),
        ];

        // ── Recent 10 transactions for the Zusammenfassung tab ────────────────
        // Member names are intentionally excluded (privacy on the summary view).
        $recentTransactions = TreasuryTransaction::with(['account', 'category'])
            ->whereIn('account_id', $visibleIds)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $recentJs = [];
        foreach ($recentTransactions as $tx) {
            $recentJs[] = [
                'id'               => $tx->id,
                'account_id'       => $tx->account_id,
                'account_name'     => $tx->account?->name,
                'category_name'    => $tx->category?->name,
                'category_color'   => $tx->category?->color ?? 'gray',
                'type'             => $tx->type,
                'amount'           => number_format((float) $tx->amount, 2, '.', ''),
                'description'      => $tx->description,
                'transaction_date' => $tx->transaction_date?->format('d.m.Y'),
            ];
        }

        // ── Buchungen tab: filtered income and expense queries ─────────────────
        // Build the base constraint and apply filters once; then split by type.
        $incomeBase  = TreasuryTransaction::with(['account', 'category'])
            ->whereIn('account_id', $visibleIds)
            ->where('type', 'income');

        $expenseBase = TreasuryTransaction::with(['account', 'category'])
            ->whereIn('account_id', $visibleIds)
            ->where('type', 'expense');

        $filters = [
            'account'   => $request->input('account'),
            'category'  => $request->input('category'),
            'date_from' => $request->input('date_from'),
            'date_to'   => $request->input('date_to'),
            'q'         => $request->input('q'),
        ];

        foreach ([$incomeBase, $expenseBase] as $q) {
            if (! empty($filters['account']) && in_array((int) $filters['account'], $visibleIds, true)) {
                $q->where('account_id', $filters['account']);
            }
            if (! empty($filters['category'])) {
                $q->where('category_id', $filters['category']);
            }
            if (! empty($filters['date_from'])) {
                $q->whereDate('transaction_date', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $q->whereDate('transaction_date', '<=', $filters['date_to']);
            }
            if (! empty($filters['q'])) {
                $q->where('description', 'like', '%' . $filters['q'] . '%');
            }
        }

        // Filtered column sums (for Buchungen tab headers)
        $filteredIncomeSum  = (float) (clone $incomeBase)->sum('amount');
        $filteredExpenseSum = (float) (clone $expenseBase)->sum('amount');

        // Transaction lists (capped at 50 per column — use the filter to narrow down)
        $incomeTransactions  = (clone $incomeBase)->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->limit(50)->get();
        $expenseTransactions = (clone $expenseBase)->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->limit(50)->get();

        // Teams for account visibility picker (optional module)
        $teams = class_exists(\Modules\Teams\Models\Team::class)
            ? \Modules\Teams\Models\Team::orderBy('name')->get()
            : collect();

        // ── Beitraege tab: contribution task metas with eager loading ──────────
        // Loaded here instead of in the view to avoid @php blocks (ANALYSEMETHODIK 4.18).
        $taskMetas = TreasuryTaskMeta::with(['task', 'account', 'payments.member'])
            ->whereIn('account_id', $visibleIds)
            ->get();

        // Pre-compute paid/total counts per task meta to avoid @php in Blade.
        $taskMetaStats = [];
        foreach ($taskMetas as $meta) {
            $paid = 0;
            foreach ($meta->payments as $payment) {
                if ($payment->isPaid()) {
                    $paid++;
                }
            }
            $taskMetaStats[$meta->id] = [
                'paid'  => $paid,
                'total' => $meta->payments->count(),
            ];
        }

        // ── JS data bridges — foreach only, no fn() / map() ──────────────────

        // toJsOption() returns: id, name, description, visibility, parent_id
        $accountsJs = [];
        foreach ($visibleAccounts as $a) {
            $accountsJs[$a->id] = $a->toJsOption();
        }

        // toJsOption() returns: id, name, transaction_type, color
        $categoriesJs = [];
        foreach ($categories as $c) {
            $categoriesJs[$c->id] = $c->toJsOption();
        }

        $transactionsJs = [];
        foreach ($incomeTransactions->merge($expenseTransactions) as $tx) {
            $transactionsJs[$tx->id] = [
                'id'               => $tx->id,
                'account_id'       => $tx->account_id,
                'category_id'      => $tx->category_id,
                'type'             => $tx->type,
                'amount'           => number_format((float) $tx->amount, 2, '.', ''),
                'description'      => $tx->description,
                'transaction_date' => $tx->transaction_date?->format('Y-m-d'),
                'reference_number' => $tx->reference_number,
                'member_id'        => $tx->member_id,
                'event_id'         => $tx->event_id,
                'task_id'          => $tx->task_id,
            ];
        }

        $parentAccountsJs = [];
        foreach ($visibleAccounts->whereNull('parent_id') as $a) {
            $parentAccountsJs[$a->id] = ['id' => $a->id, 'name' => $a->name];
        }

        $teamsJs = [];
        foreach ($teams as $tm) {
            $teamsJs[$tm->id] = ['id' => $tm->id, 'name' => $tm->name];
        }

        $membersJs = [];
        if (class_exists(Member::class)) {
            $members = Member::orderBy('last_name')->get();
            foreach ($members as $m) {
                $membersJs[$m->id] = ['id' => $m->id, 'name' => $m->last_name . ', ' . $m->first_name];
            }
        }

        return view('treasury::index', compact(
            'visibleAccounts',
            'categories',
            'incomeTransactions',
            'expenseTransactions',
            'teams',
            'totalIncome',
            'totalExpense',
            'totalBalance',
            'filteredIncomeSum',
            'filteredExpenseSum',
            'accountStats',
            'globalStats',
            'recentJs',
            'accountsJs',
            'categoriesJs',
            'transactionsJs',
            'parentAccountsJs',
            'teamsJs',
            'membersJs',
            'filters',
            'taskMetas',
            'taskMetaStats'
        ));
    }

    // ── Transactions ──────────────────────────────────────────────────────────

    /**
     * Creates a new transaction from validated form data.
     */
    public function storeTransaction(StoreTransactionRequest $request): RedirectResponse
    {
        $data               = $request->validated();
        $data['created_by'] = $request->user()->id;

        TreasuryTransaction::create($data);

        return redirect()->route('treasury.index', ['tab' => 'buchungen'])
            ->with('success', 'Buchung erfasst.');
    }

    /**
     * Updates an existing transaction from validated form data.
     */
    public function updateTransaction(UpdateTransactionRequest $request, TreasuryTransaction $transaction): RedirectResponse
    {
        $transaction->update($request->validated());

        return redirect()->route('treasury.index', ['tab' => 'buchungen'])
            ->with('success', 'Buchung aktualisiert.');
    }

    /**
     * Permanently deletes a transaction.
     */
    public function destroyTransaction(TreasuryTransaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return redirect()->route('treasury.index', ['tab' => 'buchungen'])
            ->with('success', 'Buchung gelöscht.');
    }

    // ── Accounts ──────────────────────────────────────────────────────────────

    /**
     * Creates a new treasury account from validated form data.
     */
    public function storeAccount(StoreAccountRequest $request): RedirectResponse
    {
        $data               = $request->validated();
        $data['created_by'] = $request->user()->id;
        $teamIds            = $data['team_ids'] ?? [];
        unset($data['team_ids']);

        $account = TreasuryAccount::create($data);

        if ($data['visibility'] === 'team_restricted' && ! empty($teamIds)) {
            $account->teams()->sync($teamIds);
        }

        return redirect()->route('treasury.index', ['tab' => 'konten'])
            ->with('success', 'Konto angelegt.');
    }

    /**
     * Updates an existing treasury account from validated form data.
     */
    public function updateAccount(UpdateAccountRequest $request, TreasuryAccount $account): RedirectResponse
    {
        $data    = $request->validated();
        $teamIds = $data['team_ids'] ?? [];
        unset($data['team_ids']);

        $account->update($data);

        if ($data['visibility'] === 'team_restricted') {
            $account->teams()->sync($teamIds);
        } else {
            $account->teams()->detach();
        }

        return redirect()->route('treasury.index', ['tab' => 'konten'])
            ->with('success', 'Konto aktualisiert.');
    }

    /**
     * Deletes a treasury account.
     *
     * Accounts that have transactions or sub-accounts cannot be deleted.
     */
    public function destroyAccount(TreasuryAccount $account): RedirectResponse
    {
        if ($account->transactions()->exists()) {
            return redirect()->route('treasury.index', ['tab' => 'konten'])
                ->with('error', 'Konto kann nicht gelöscht werden – es enthält noch Buchungen.');
        }

        if ($account->children()->exists()) {
            return redirect()->route('treasury.index', ['tab' => 'konten'])
                ->with('error', 'Konto kann nicht gelöscht werden – es enthält noch Unterkonten.');
        }

        $account->teams()->detach();
        $account->delete();

        return redirect()->route('treasury.index', ['tab' => 'konten'])
            ->with('success', 'Konto gelöscht.');
    }

    // ── Categories ────────────────────────────────────────────────────────────

    /**
     * Creates a new transaction category from validated form data.
     */
    public function storeCategory(StoreCategoryRequest $request): RedirectResponse
    {
        $data               = $request->validated();
        $data['created_by'] = $request->user()->id;

        TreasuryCategory::create($data);

        return redirect()->back()->with('success', 'Kategorie angelegt.');
    }

    /**
     * Updates an existing category from validated form data.
     */
    public function updateCategory(UpdateCategoryRequest $request, TreasuryCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->back()->with('success', 'Kategorie aktualisiert.');
    }

    /**
     * Deletes a category.
     *
     * Linked transactions will have their category_id set to null (nullOnDelete FK).
     */
    public function destroyCategory(TreasuryCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->back()->with('success', 'Kategorie gelöscht.');
    }
}
