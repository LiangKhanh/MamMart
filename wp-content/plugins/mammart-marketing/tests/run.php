<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once dirname(__DIR__) . '/src/bootstrap.php';

use MamMart\Marketing\FakeClock;
use MamMart\Marketing\FakeMailer;
use MamMart\Marketing\FakeRepository;
use MamMart\Marketing\MarketingService;

final class BlockedTest extends RuntimeException {}
$root = dirname(__DIR__);
$seed = json_decode((string) file_get_contents($root . '/fixtures/scenarios.json'), true, 512, JSON_THROW_ON_ERROR);
$baseConfig = require $root . '/config/sandbox.php';
$runDir = sys_get_temp_dir() . '/mammart-ph-tests-' . bin2hex(random_bytes(8));
$logs = [];
$repositories = [];

function expect($actual, $expected, string $message = ''): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($message . ' expected=' . json_encode($expected) . ' actual=' . json_encode($actual));
    }
}

function context(?array $data = null, string $time = '2026-11-06 09:00:00', array $config = [], bool $mailFail = false): array
{
    global $seed, $baseConfig, $runDir, $repositories;
    $repository = new FakeRepository($runDir . '/' . count($repositories), $data ?? $seed);
    $repositories[] = $repository;
    $settings = array_replace($baseConfig, $config);
    return [new MarketingService($repository, new FakeClock($time, $settings['timezone']), new FakeMailer($mailFail), $settings), $repository];
}

function serviceAt(FakeRepository $repository, string $time, array $config = [], bool $fail = false): MarketingService
{
    global $baseConfig;
    $settings = array_replace($baseConfig, $config);
    return new MarketingService($repository, new FakeClock($time, $settings['timezone']), new FakeMailer($fail), $settings);
}

function pricing(): array { return ['pricing_policy' => 'SIMULATION_BYPASS_MIN_CAP']; }
function cart(): array { global $seed; return $seed['carts']['mixed']; }
function issued(MarketingService $service, string $baby = 'B-DEMO-01'): array
{
    $result = $service->issue($baby);
    expect($result['status'], 'ISSUED');
    return $result['issue'];
}
function dobEvent(string $date, int $version = 2, string $id = 'EV-DEMO-DOB-01'): array
{
    return ['event_id' => $id, 'type' => 'BabyDobApproved', 'baby_id' => 'B-DEMO-01',
        'customer_id' => 'C-DEMO-01', 'birth_date' => $date, 'dob_version' => $version];
}
function useEvent(array $coupon, string $id = 'EV-DEMO-USE-01', string $order = 'O-DEMO-USE-01'): array
{
    return ['event_id' => $id, 'type' => 'SimulatedRedemptionSucceeded', 'order_id' => $order,
        'customer_id' => 'C-DEMO-01', 'code' => $coupon['code']];
}

$tests = [
    'T01' => ['Consent defaults false; profile flags do not opt in', function (): void {
        global $seed;
        $data = $seed; unset($data['consents']['C-DEMO-01']);
        [$s, $r] = context($data);
        expect($s->issue('B-DEMO-01')['status'], 'NO_CONSENT');
        $before = $r->snapshot()['babies']['B-DEMO-01']['birth_date'];
        expect($s->setConsent('C-DEMO-01', true)['status'], 'CONSENT_SAVED');
        expect($r->snapshot()['babies']['B-DEMO-01']['birth_date'], $before);
        expect($s->setConsent('C-DEMO-01', false)['marketing_opt_in'], false);
        expect(count($r->snapshot()['consent_history']), 2);
    }],
    'T02' => ['Before six-month milestone', function (): void {
        [$s] = context(null, '2026-11-05 23:59:59'); expect($s->issue('B-DEMO-01')['status'], 'NOT_DUE');
    }],
    'T03' => ['Issue on milestone and expire at next month boundary', function (): void {
        [$s] = context(); $c = issued($s);
        expect($c['expires_at_exclusive'], '2026-12-01 00:00:00'); expect($c['percent'], 15);
    }],
    'T04' => ['Late signup waits until next calendar day under explicit trial policy', function (): void {
        global $seed; $d = $seed;
        $d['babies']['B-DEMO-01']['birth_date'] = '2026-05-15';
        $d['babies']['B-DEMO-01']['registered_at'] = '2026-11-20 18:30:00';
        [$s, $r] = context($d, '2026-11-20 19:00:00');
        expect($s->issue('B-DEMO-01')['status'], 'PENDING_POLICY');
        $cfg = ['late_registration_policy' => 'SIMULATION_CALENDAR_NEXT_DAY'];
        expect(serviceAt($r, '2026-11-20 23:59:59', $cfg)->issue('B-DEMO-01')['status'], 'WAIT_NEXT_DAY');
        issued(serviceAt($r, '2026-11-21 00:00:00', $cfg));
    }],
    'T05' => ['Last-day signup receives no next-month catch-up', function (): void {
        global $seed; $d = $seed;
        $d['babies']['B-DEMO-01']['registered_at'] = '2026-11-30 09:00:00';
        [$s, $r] = context($d, '2026-11-30 10:00:00', ['late_registration_policy' => 'SIMULATION_CALENDAR_NEXT_DAY']);
        expect($s->issue('B-DEMO-01')['status'], 'OUTSIDE_CAMPAIGN_MONTH');
        expect(serviceAt($r, '2026-12-01 00:00:00')->issue('B-DEMO-01')['status'], 'OUTSIDE_CAMPAIGN_MONTH');
    }],
    'T06' => ['Repeated issuance is idempotent', function (): void {
        [$s, $r] = context(); $c = issued($s);
        for ($i = 0; $i < 5; $i++) { $again = $s->issue('B-DEMO-01'); expect($again['status'], 'ALREADY_ISSUED'); expect($again['issue']['code'], $c['code']); }
        expect(count($r->snapshot()['issues']), 1);
    }],
    'T07' => ['Two PHP processes contend for the same issuance', function (): void {
        global $root, $runDir, $seed;
        if (!function_exists('proc_open')) { throw new BlockedTest('proc_open is unavailable.'); }
        $directory = $runDir . '/concurrency';
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, $root . '/tests/issue-worker.php', $directory, 'barrier'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) { throw new BlockedTest('Cannot spawn PHP worker.'); }
            fclose($pipes[0]); $processes[] = [$process, $pipes];
        }
        $statuses = []; $codes = [];
        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            expect(proc_close($process), 0, $stderr);
            expect(trim($stderr), '', 'Worker must not emit PHP warnings');
            try {
                $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new RuntimeException('Worker returned invalid JSON: ' . $error->getMessage()
                    . '; stdout=' . substr($stdout, 0, 2000) . '; stderr=' . substr($stderr, 0, 2000));
            }
            $statuses[] = $result['status']; $codes[] = $result['issue']['code'];
        }
        sort($statuses); expect($statuses, ['ALREADY_ISSUED', 'ISSUED']); expect($codes[0], $codes[1]);
        expect(count((new FakeRepository($directory, $seed))->snapshot()['issues']), 1);
    }],
    'T08' => ['Two babies, one parent, separate benefits', function (): void {
        [$s, $r] = context(); $a = issued($s); $b = issued($s, 'B-DEMO-03');
        expect($a['code'] === $b['code'], false); expect(count($r->snapshot()['issues']), 2);
    }],
    'T09' => ['Missing, impossible, future and unapproved DOB', function (): void {
        global $seed;
        foreach (['', '2026-02-30', '2026-12-01'] as $date) {
            $d = $seed; $d['babies']['B-DEMO-01']['birth_date'] = $date;
            [$s] = context($d); expect($s->issue('B-DEMO-01')['status'], 'INVALID_DOB');
        }
        $d = $seed; $d['babies']['B-DEMO-01']['dob_approved'] = false;
        [$s] = context($d); expect($s->issue('B-DEMO-01')['status'], 'INVALID_DOB');
    }],
    'T10' => ['Approved DOB replaces old schedule before issuance', function (): void {
        [$s, $r] = context(); expect($s->approveDob(dobEvent('2026-05-20'))['status'], 'DOB_APPROVED');
        expect($s->issue('B-DEMO-01')['status'], 'NOT_DUE');
        $c = issued(serviceAt($r, '2026-11-20 09:00:00')); expect($c['dob_version_at_issue'], 2);
    }],
    'T11' => ['DOB changed after issuance does not create or rewrite benefit', function (): void {
        [$s, $r] = context(); $c = issued($s);
        expect($s->approveDob(dobEvent('2026-06-06'))['existing_issue_policy'], 'PENDING_POLICY');
        $again = $s->issue('B-DEMO-01'); expect($again['status'], 'ALREADY_ISSUED');
        expect($again['issue']['expires_at_exclusive'], $c['expires_at_exclusive']); expect(count($r->snapshot()['issues']), 1);
    }],
    'T12' => ['Duplicate, stale and wrong-owner DOB events', function (): void {
        [$s, $r] = context(); $e = dobEvent('2026-05-20'); $s->approveDob($e);
        expect($s->approveDob($e)['status'], 'DUPLICATE_EVENT');
        expect($s->approveDob(dobEvent('2026-05-01', 1, 'EV-DEMO-OLD'))['status'], 'STALE_VERSION');
        $e = dobEvent('2026-05-01', 3, 'EV-DEMO-WRONG'); $e['customer_id'] = 'C-DEMO-02';
        expect($s->approveDob($e)['status'], 'INVALID_EVENT');
        expect($r->snapshot()['babies']['B-DEMO-01']['birth_date'], '2026-05-20');
    }],
    'T13' => ['Unresolved 29/30/31-day boundaries including leap-year February', function (): void {
        global $seed;
        foreach ([['2026-05-31', '2026-11-30 09:00:00'], ['2026-08-29', '2027-02-28 09:00:00'],
            ['2026-08-30', '2027-02-28 09:00:00'], ['2027-08-31', '2028-02-29 09:00:00']] as [$dob, $time]) {
            $d = $seed; $d['babies']['B-DEMO-01']['birth_date'] = $dob;
            [$s] = context($d, $time); expect($s->issue('B-DEMO-01')['status'], 'PENDING_POLICY');
        }
    }],
    'T14' => ['Withdraw before issuance stops issuance and sending', function (): void {
        [$s, $r] = context(); $s->setConsent('C-DEMO-01', false);
        expect($s->issue('B-DEMO-01')['status'], 'NO_CONSENT'); expect(count($r->snapshot()['issues']), 0);
    }],
    'T15' => ['Withdraw after issuance retains old usable coupon', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s); $s->setConsent('C-DEMO-01', false);
        expect($s->validate($c['code'], cart())['status'], 'VALID'); expect($s->send($c['coupon_id'])['status'], 'NO_CONSENT');
    }],
    'T16' => ['Failed email retries the same persisted code', function (): void {
        [$s, $r] = context(null, '2026-11-06 09:00:00', [], true); $c = issued($s);
        expect($s->send($c['coupon_id'])['status'], 'FAILED');
        $mail = serviceAt($r, '2026-11-06 10:00:00')->send($c['coupon_id']);
        expect($mail['status'], 'SIMULATED_SENT'); expect($mail['coupon_code'], $c['code']); expect(count($r->snapshot()['issues']), 1);
    }],
    'T17' => ['Consent checked again immediately before retry', function (): void {
        [$s, $r] = context(null, '2026-11-06 09:00:00', [], true); $c = issued($s); $s->send($c['coupon_id']);
        $s->setConsent('C-DEMO-01', false);
        expect(serviceAt($r, '2026-11-06 10:00:00')->send($c['coupon_id'])['status'], 'NO_CONSENT');
        expect(count($r->snapshot()['issues']), 1);
    }],
    'T18' => ['Anonymous or another customer cannot redeem', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s);
        foreach (['', 'C-DEMO-02'] as $owner) { $cart = cart(); $cart['customer_id'] = $owner; expect($s->validate($c['code'], $cart)['status'], 'WRONG_OWNER'); }
    }],
    'T19' => ['Expiry inclusive end of month and exclusive next-month midnight', function (): void {
        [$s, $r] = context(); $c = issued($s);
        expect(serviceAt($r, '2026-11-30 23:59:59', pricing())->validate($c['code'], cart())['status'], 'VALID');
        $expired = serviceAt($r, '2026-12-01 00:00:00', pricing());
        expect($expired->validate($c['code'], cart())['status'], 'EXPIRED'); expect($expired->send($c['coupon_id'])['status'], 'EXPIRED');
    }],
    'T20' => ['Eligible-only basket discounts fifteen percent', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s); $cart = cart();
        $cart['lines'] = [['sku' => 'SKU-DEMO-BOWL', 'quantity' => 2, 'unit_price_vnd' => 1, 'eligible' => false]];
        $v = $s->validate($c['code'], $cart); expect($v['status'], 'VALID'); expect($v['discount_vnd'], 60000); expect($v['total_vnd'], 360000);
    }],
    'T21' => ['Mixed basket excludes food and other items; shipping unchanged', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s); $cart = cart();
        $v = $s->validate($c['code'], $cart); expect($v['discount_vnd'], 60000); expect($v['total_vnd'], 480000); expect($v['shipping_vnd'], 20000);
        $cart['lines'][] = ['sku' => 'SKU-DEMO-OTHER', 'quantity' => 1]; $cart['shipping_vnd'] = 30000;
        $v = $s->validate($c['code'], $cart); expect($v['discount_vnd'], 60000); expect($v['total_vnd'], 570000);
    }],
    'T22' => ['Food-only and unrelated-only baskets cannot get discount', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s);
        foreach (['SKU-DEMO-FOOD', 'SKU-DEMO-OTHER'] as $sku) { $cart = cart(); $cart['lines'] = [['sku' => $sku, 'quantity' => 1]]; expect($s->validate($c['code'], $cart)['status'], 'NO_ELIGIBLE_ITEMS'); }
        global $seed; $data = $seed; $data['products']['SKU-DEMO-FOOD']['eligible'] = true;
        [$s] = context($data, '2026-11-06 09:00:00', pricing()); $c = issued($s);
        $cart = cart(); $cart['lines'] = [['sku' => 'SKU-DEMO-FOOD', 'quantity' => 1]];
        expect($s->validate($c['code'], $cart)['status'], 'NO_ELIGIBLE_ITEMS');
    }],
    'T23' => ['Variation eligibility must be explicit; no parent inheritance', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s); $cart = cart();
        $cart['lines'] = [['sku' => 'SKU-DEMO-VARIANT', 'quantity' => 1]];
        expect($s->validate($c['code'], $cart)['status'], 'PENDING_POLICY');
    }],
    'T24' => ['One coupon per cart and per order, both application orders', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $a = issued($s); $b = issued($s, 'B-DEMO-03');
        foreach ([[$b['coupon_id']], [$a['coupon_id'], $b['coupon_id']], [$b['coupon_id'], $a['coupon_id']]] as $ids) {
            $cart = cart(); $cart['coupon_ids'] = $ids; expect($s->validate($a['code'], $cart)['status'], 'ONE_COUPON_PER_ORDER');
        }
        expect($s->useCoupon(useEvent($a), cart())['status'], 'SIMULATED_USED');
        expect($s->useCoupon(useEvent($b, 'EV-DEMO-SECOND'), cart())['status'], 'ONE_COUPON_PER_ORDER');
    }],
    'T25' => ['Successful fake use prevents subsequent use', function (): void {
        [$s] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s);
        expect($s->useCoupon(useEvent($c), cart())['status'], 'SIMULATED_USED');
        expect($s->validate($c['code'], cart())['status'], 'ALREADY_USED');
        expect($s->useCoupon(useEvent($c, 'EV-DEMO-USE-02', 'O-DEMO-USE-02'), cart())['status'], 'ALREADY_USED');
    }],
    'T26' => ['Duplicate success event cannot consume twice', function (): void {
        [$s, $r] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s); $event = useEvent($c);
        $s->useCoupon($event, cart()); expect($s->useCoupon($event, cart())['status'], 'DUPLICATE_EVENT');
        expect(count($r->snapshot()['events']), 1);
    }],
    'T27' => ['Pending, paid, cancelled, partial and full refunds have no automatic usage mapping', function (): void {
        [$s, $r] = context(null, '2026-11-06 09:00:00', pricing()); $c = issued($s);
        foreach (['Pending_Payment', 'Paid', 'Completed', 'Cancelled', 'PartiallyRefunded', 'Refunded'] as $type) {
            $event = useEvent($c, 'EV-DEMO-' . $type); $event['type'] = $type;
            expect($s->useCoupon($event, cart())['status'], 'PENDING_POLICY');
        }
        expect($s->validate($c['code'], cart())['status'], 'VALID');
    }],
    'T28' => ['Unconfirmed pricing blocks; explicitly labelled minimum, cap and rounding trials', function (): void {
        [$s, $r] = context(); $c = issued($s); expect($s->validate($c['code'], cart())['status'], 'PENDING_POLICY');
        foreach ([399999 => 'VALID', 400000 => 'VALID', 400001 => 'MINIMUM_NOT_MET'] as $minimum => $status) {
            $cfg = ['pricing_policy' => 'SIMULATION_EXPLICIT_MIN_CAP', 'simulation_min_eligible_vnd' => $minimum, 'simulation_cap_vnd' => 50000];
            $v = serviceAt($r, '2026-11-06 10:00:00', $cfg)->validate($c['code'], cart()); expect($v['status'], $status);
            if ($status === 'VALID') { expect($v['discount_vnd'], 50000); expect($v['label'], 'SIMULATION ONLY'); }
        }
        foreach ([59999 => 59999, 60000 => 60000, 60001 => 60000] as $cap => $expected) {
            $cfg = ['pricing_policy' => 'SIMULATION_EXPLICIT_MIN_CAP', 'simulation_min_eligible_vnd' => 0, 'simulation_cap_vnd' => $cap];
            expect(serviceAt($r, '2026-11-06 10:00:00', $cfg)->validate($c['code'], cart())['discount_vnd'], $expected);
        }
        global $seed; $d = $seed; $d['products']['SKU-DEMO-BOWL']['unit_price_vnd'] = 101;
        [$s, $r] = context($d, '2026-11-06 09:00:00', pricing()); $c = issued($s); $cart = cart(); $cart['lines'] = [['sku' => 'SKU-DEMO-BOWL', 'quantity' => 1]];
        expect($s->validate($c['code'], $cart)['status'], 'PENDING_POLICY');
        $v = serviceAt($r, '2026-11-06 10:00:00', pricing() + ['simulation_rounding' => 'SIMULATION_HALF_UP_VND'])->validate($c['code'], $cart);
        expect($v['discount_vnd'], 15);
    }],
    'T29' => ['Restart after durable issue-before-send; transaction rollback and invalid inputs', function (): void {
        [$s, $r] = context(); $c = issued($s); unset($s);
        $s = serviceAt($r, '2026-11-06 10:00:00', pricing()); expect($s->issue('B-DEMO-01')['issue']['code'], $c['code']);
        expect($s->send($c['coupon_id'])['status'], 'SIMULATED_SENT');
        try { $r->transaction(static function (array &$state): array { $state['issues'] = []; throw new RuntimeException('injected failure'); }); } catch (RuntimeException $e) { expect($e->getMessage(), 'injected failure'); }
        expect(count($r->snapshot()['issues']), 1);
        $cart = cart(); $cart['lines'][0]['quantity'] = -1; expect($s->validate($c['code'], $cart)['status'], 'INVALID_DATA');
        $cart = cart(); $cart['lines'][0]['sku'] = 'UNKNOWN'; expect($s->validate($c['code'], $cart)['status'], 'INVALID_DATA');
        $cart = cart(); $cart['shipping_vnd'] = -1; expect($s->validate($c['code'], $cart)['status'], 'INVALID_DATA');
        global $baseConfig;
        foreach ([['mode' => 'production'], ['label' => 'LIVE'], ['discount_percent' => 10]] as $override) {
            $blocked = false;
            try { new MarketingService($r, new FakeClock('2026-11-06 10:00:00'), new FakeMailer(), array_replace($baseConfig, $override)); }
            catch (InvalidArgumentException $e) { $blocked = true; }
            expect($blocked, true, 'Production/config guard');
        }
    }],
    'T30' => ['Live WordPress role and nonce protection', function (): void {
        throw new BlockedTest('CLI runner does not execute live WordPress sessions. Manual T30 evidence is tracked separately; do not mark live nonce/role checks PASS from CLI or stub tests.');
    }],
];

$results = [];
$counts = ['PASS' => 0, 'FAIL' => 0, 'BLOCKED' => 0];
foreach ($tests as $id => [$name, $test]) {
    $status = 'PASS'; $detail = 'Assertions executed successfully.';
    try { $test(); }
    catch (BlockedTest $e) { $status = 'BLOCKED'; $detail = $e->getMessage(); }
    catch (Throwable $e) { $status = 'FAIL'; $detail = $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine(); }
    $counts[$status]++;
    $results[] = compact('id', 'name', 'status', 'detail');
    echo $id . ' ' . $status . ' — ' . $name . ($status === 'PASS' ? '' : ': ' . $detail) . PHP_EOL;
}
foreach ($repositories as $index => $repository) {
    foreach ($repository->snapshot()['logs'] as $entry) { $logs[] = ['context' => $index] + $entry; }
}
$output = $root . '/reports';
if (!is_dir($output)) { mkdir($output, 0700, true); }
$report = ['label' => 'SIMULATION ONLY', 'executed_at_utc' => gmdate('c'), 'php_version' => PHP_VERSION,
    'php_binary' => PHP_BINARY, 'counts' => $counts, 'results' => $results,
    'limitations' => ['No live WooCommerce, email or payment integration.', 'PASS of PENDING_POLICY asserts safe deferral, not a resolved business policy.']];
file_put_contents($output . '/test-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
file_put_contents($output . '/test-audit-log.json', json_encode(['label' => 'SIMULATION ONLY', 'logs' => $logs], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
$markdown = "# T2-PH — kết quả thực thi\n\nSIMULATION ONLY. PHP " . PHP_VERSION . ". Executed UTC: " . $report['executed_at_utc'] . "\n\n";
$markdown .= 'PASS: ' . $counts['PASS'] . '; FAIL: ' . $counts['FAIL'] . '; BLOCKED: ' . $counts['BLOCKED'] . ".\n\n| ID | Test | Result | Evidence / limitation |\n|---|---|---|---|\n";
foreach ($results as $result) { $markdown .= '| ' . implode(' | ', array_map(static function ($v) { return str_replace('|', '/', $v); }, $result)) . " |\n"; }
$markdown .= "\nPENDING_POLICY cases test safe deferral only. T30 requires a live WordPress environment. No actual checkout or email test has passed.\n";
file_put_contents($output . '/test-results.md', $markdown);
echo json_encode($counts) . PHP_EOL;
exit($counts['FAIL'] > 0 ? 1 : 0);
