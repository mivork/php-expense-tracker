#!/usr/bin/env php
<?php
declare(strict_types=1);

const DATA_FILE = __DIR__ . '/expenses.csv';
const CSV_HEADER = ['date', 'category', 'amount', 'memo'];
const CATEGORIES = ['食費', '交通費', '日用品', '娯楽', '通信費', '家賃', '光熱費', 'その他'];

function load_expenses(): array
{
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    $handle = fopen(DATA_FILE, 'r');
    if ($handle === false) {
        return [];
    }
    $header = fgetcsv($handle);
    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 4) {
            continue;
        }
        $rows[] = [
            'date' => $data[0],
            'category' => $data[1],
            'amount' => (int) $data[2],
            'memo' => $data[3],
        ];
    }
    fclose($handle);
    return $rows;
}

function save_expenses(array $expenses): void
{
    $handle = fopen(DATA_FILE, 'w');
    fputcsv($handle, CSV_HEADER);
    foreach ($expenses as $e) {
        fputcsv($handle, [$e['date'], $e['category'], $e['amount'], $e['memo']]);
    }
    fclose($handle);
}

function cmd_add(array $args): void
{
    if (count($args) < 2) {
        echo "使い方: php main.php add <金額> <カテゴリ> [メモ]\n";
        echo "  例: php main.php add 1200 食費 スーパー\n";
        return;
    }
    $amount = (int) $args[0];
    if ($amount <= 0) {
        echo "エラー: 金額は正の整数で指定してください\n";
        return;
    }
    $category = $args[1];
    if (!in_array($category, CATEGORIES, true)) {
        echo "エラー: 不明なカテゴリ「{$category}」\n";
        echo 'カテゴリ一覧: ' . implode(' / ', CATEGORIES) . "\n";
        return;
    }
    $memo = $args[2] ?? '';
    $expenses = load_expenses();
    $expenses[] = [
        'date' => date('Y-m-d'),
        'category' => $category,
        'amount' => $amount,
        'memo' => $memo,
    ];
    save_expenses($expenses);
    echo "追加しました: {$amount}円 [{$category}] {$memo}\n";
}

function cmd_list(array $args): void
{
    $month_filter = null;
    $idx = array_search('--month', $args, true);
    if ($idx !== false && isset($args[$idx + 1])) {
        $month_filter = $args[$idx + 1];
    }
    $expenses = load_expenses();
    if (empty($expenses)) {
        echo "データがありません\n";
        return;
    }
    // --month YYYY-MM でフィルタ
    $month = $month_filter;
    $total = 0;
    foreach ($expenses as $e) {
        if ($month !== null && !str_starts_with($e['date'], $month)) {
            continue;
        }
        $total += $e['amount'];
        printf("%s  %-8s %8d円  %s\n", $e['date'], $e['category'], $e['amount'], $e['memo']);
    }
    echo "------------------------------\n";
    echo "合計: {$total}円\n";
}

function cmd_summary(array $args): void
{
    $month_filter = null;
    $idx = array_search('--month', $args, true);
    if ($idx !== false && isset($args[$idx + 1])) {
        $month_filter = $args[$idx + 1];
    }
    $expenses = load_expenses();
    if (empty($expenses)) {
        echo "データがありません\n";
        return;
    }
    // --month YYYY-MM でフィルタ
    $month = $month_filter;
    $by_category = [];
    $total = 0;
    foreach ($expenses as $e) {
        if ($month !== null && !str_starts_with($e['date'], $month)) {
            continue;
        }
        $by_category[$e['category']] = ($by_category[$e['category']] ?? 0) + $e['amount'];
        $total += $e['amount'];
    }
    arsort($by_category);
    $label = $month_filter !== null ? "{$month_filter}の" : '全期間の';
    echo "{$label}カテゴリ別集計:\n";
    foreach ($by_category as $category => $amount) {
        $bar = str_repeat('#', (int) min($amount / 1000, 40));
        printf("  %-8s %8d円  %s\n", $category, $amount, $bar);
    }
    echo "------------------------------\n";
    echo "合計: {$total}円\n";
}

function show_help(): void
{
    echo <<<'HELP'
家計簿CLI

使い方:
  php main.php add <金額> <カテゴリ> [メモ]  支出を追加
  php main.php list [--month YYYY-MM]        支出一覧を表示
  php main.php summary [--month YYYY-MM]     カテゴリ別集計を表示

例:
  php main.php add 1200 食費 スーパー
  php main.php list
  php main.php list --month 2026-11
  php main.php summary --month 2026-11

カテゴリ: 食費 / 交通費 / 日用品 / 娯楽 / 通信費 / 家賃 / 光熱費 / その他
HELP;
}

$command = $argv[1] ?? null;
$args = array_slice($argv, 2);

match ($command) {
    'add' => cmd_add($args),
    'list' => cmd_list($args),
    'summary' => cmd_summary($args),
    default => show_help(),
};
