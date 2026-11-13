<?php
declare(strict_types=1);

namespace MamMart\Marketing;

/** A complete fake lifecycle, shared by CLI and the protected WordPress screen. */
final class Demo
{
    public static function run(string $storage): array
    {
        $root = dirname(__DIR__);
        $seed = json_decode((string) file_get_contents($root . '/fixtures/scenarios.json'), true, 512, JSON_THROW_ON_ERROR);
        $config = require $root . '/config/sandbox.php';
        $config['pricing_policy'] = 'SIMULATION_BYPASS_MIN_CAP';
        $repository = new FakeRepository($storage, $seed);
        $clock = new FakeClock($config['as_of'], $config['timezone']);
        $service = new MarketingService($repository, $clock, new FakeMailer(true), $config);
        $issued = $service->issue('B-DEMO-01');
        $coupon = $issued['issue'];
        $results = ['issue' => $issued, 'duplicate' => $service->issue('B-DEMO-01'),
            'failed_mail' => $service->send($coupon['coupon_id'])];
        $service = new MarketingService($repository, $clock, new FakeMailer(), $config);
        $results['retry_same_coupon'] = $service->send($coupon['coupon_id']);
        $results['checkout'] = $service->validate($coupon['code'], $seed['carts']['mixed']);
        $results['use'] = $service->useCoupon(['event_id' => 'EV-DEMO-USE-01',
            'type' => 'SimulatedRedemptionSucceeded', 'order_id' => 'O-DEMO-USE-01',
            'customer_id' => 'C-DEMO-01', 'code' => $coupon['code']], $seed['carts']['mixed']);
        $results['opt_out'] = $service->setConsent('C-DEMO-01', false);
        $results['blocked_retry'] = $service->send($coupon['coupon_id']);
        return ['label' => 'SIMULATION ONLY', 'real_email_sent' => false, 'real_woo_records_created' => false,
            'configuration' => $config, 'results' => $results, 'state' => $repository->snapshot()];
    }
}
