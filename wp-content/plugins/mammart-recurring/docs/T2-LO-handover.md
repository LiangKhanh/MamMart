# Bàn giao T2-LO

**T2 simulation only – chưa tích hợp WooCommerce Order thật.**

## Phạm vi và chạy thử

Plugin cài tại `wp-content/plugins/mammart-recurring/`. Đã có đăng ký/Plan, chu kỳ, fake order events, reminder T-2, dedupe, FakeMailer và quản lý lịch. Plugin load các class; nghiệp vụ chạy qua service/CLI. Config baseline nằm tại `config/sandbox.php`, ghi lại 21/28/35, default 28, lead 2 ngày và UTC để đối chiếu. Core hiện giữ các giá trị này cố định.

Từ thư mục plugin, dùng PHP 8.1 trở lên chạy:

```powershell
C:\wamp64\bin\php\php8.1.0\php.exe bin/test-plan.php
C:\wamp64\bin\php\php8.1.0\php.exe bin/test-reminder.php
C:\wamp64\bin\php\php8.1.0\php.exe bin/test-management.php
```

Runner cuối gồm Management và Event contract. Kết quả bàn giao là Plan 19, Reminder 15, Management 25, Event 15 — tổng 74 PASS / 0 FAIL / 0 DEFERRED. JSON/Markdown và audit log trong `reports/` chứa input/expected/actual và transition.

## Fixture và điểm nối T3

Các fixture chính trong `fixtures/` là `plan-simulation.json`, `reminder-simulation.json`, `management-simulation.json`, `event-contract-simulation.json`. Chúng chứa fake customer/product/variation/order, event envelope và thời gian test. Tất cả là SIMULATION DATA ONLY.

T1-NL nối vào adapter với event_id, event_type, event_time, order_id, customer_id, plan_id, period_id và is_first_order. T1-KH nối product_id/variation_id/SKU, eligibility `_is_recurring_eligible = 1`, giá và tồn kho. WooCommerce hooks, mapping xác thực và persistence thật dành cho T3. Xem `sandbox-contract.md` để biết chi tiết.

## CHỐT / OPEN / SANDBOX_ASSUMPTION

CHỐT gồm các chu kỳ, kích hoạt từ Completed, tính kỳ từ completion, T-2, dedupe, pause/dừng và Cancelled không resume. Sandbox dùng email giả, không tự tạo/hủy Order, thu tiền hay chạy cron/job thật.

OPEN gồm COD recurring, retry email, thời điểm áp dụng đổi cycle sau reminder và chi tiết resume lịch quá hạn.

SANDBOX_ASSUMPTION giữ expected date hiện tại khi đổi cycle. OPEN_RULE_GUARD yêu cầu chọn ngày tương lai trước khi resume lịch đã qua hoặc bằng ngày hiện tại. Legacy event normalization, mapping period giả và dedupe trong memory chỉ phục vụ T2. Các ca PASS cho guard/assumption xác nhận hành vi sandbox, không chốt business rule cuối.
