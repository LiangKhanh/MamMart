# Giao ước sandbox T2-LO

## CHỐT TỪ TUẦN 1

Eligibility theo `_is_recurring_eligible = 1` từ fixture, customer chủ động đăng ký. Plan lưu customer/product/variation/quantity/cycle, PendingActivation và chưa có ngày dự kiến. Cycle hợp lệ 21/28/35, mặc định 28.

Initial OrderCompleted kích hoạt từ PendingActivation. Completion của đơn mua lại hợp lệ tính `next_expected_date = completed_at + frequency_days` bằng ngày UTC. OrderPlaced liên kết đơn giả với cycle, giữ nguyên lịch và khiến scheduler chờ completion thay vì coi là chưa đặt. PaymentConfirmed là event legacy để tracing, không tính kỳ. OrderCancelled recurring pause plan. Đơn đầu hủy trước kích hoạt giữ PendingActivation và chặn completion muộn của đơn đó.

Reminder chỉ cho Active tại `expected_date - 2 days`. Khóa `plan_id@YYYY-MM-DD` được ghi vào memory trước khi FakeMailer chạy. SENT có sent_at, FAILED có lý do và sent_at=null. Chạy lại giữ nguyên record, chưa có retry. Audit/history cũ được giữ khi postpone.

Scheduler dùng UTC và `now_date > expected_date` để pause chu kỳ chưa có xác nhận/đơn đang chờ. Pause không tạo kỳ mới. Cancel giữ source_order_id, dừng reminder tương lai. Cancelled không resume được. Pause/cancel lặp trả changed=false và giữ một transition log.

Giá thực tế, tồn kho và thao tác đặt hàng thuộc luồng checkout T3 do customer chủ động. Sandbox không tự tạo/hủy Woo Order hoặc tự thanh toán, gửi email/SMS thật hay substitute.

## Interface và management

`RecurringPlanService::manage(planId, action, value, now)` nhận action quantity/variation/cycle/postpone/pause/resume/cancel. Quantity là integer >0, variation thuộc catalog fixture của đúng product, cycle là integer 21/28/35. Rejection giữ nguyên snapshot và ghi audit. Postpone nhận YYYY-MM-DD hợp lệ, từ ngày test hiện tại trở đi và cần plan đã có schedule. Caller cung cấp DateTimeImmutable để test deterministic. Caller CLI được coi là trusted; authorization cho customer thật thuộc T3.

## Contract T1-NL và audit adapter

Trước bước 4.4, adapter chỉ nhận order_id, plan_id, ngày hoàn tất/hủy. Thiếu event_id/event_time/customer_id/period_id/is_first_order và completion của đơn mua lại.

Envelope hiện tại có event_type cùng bảy field event_id, event_time, order_id, customer_id, plan_id, period_id, is_first_order. completed_at/cancelled_at có thể bổ sung theo loại event, mặc định event_time. Adapter chấp nhận YYYY-MM-DD hoặc ISO-8601 có timezone và chuẩn hóa tính lịch về UTC. source_ref có thể thay plan_id nếu mang mã plan.

- Khi có event_id, adapter kiểm tất cả field còn lại, boolean is_first_order, customer và liên kết đơn/period.
- Initial được phân biệt bằng is_first_order=true và đối chiếu source_order_id. Trạng thái Plan chỉ kiểm tính hợp lệ transition.
- Recurring dùng is_first_order=false. OrderPlaced có thể liên kết period_id opaque (ví dụ PERIOD-002) với expected date hiện tại. Completion/cancellation phải khớp liên kết đó. Sandbox cũng cho period_id bằng khóa canonical plan_id@expected_date để fixture mô tả cycle trực tiếp.
- Period cũ sau postpone bị reject. Order đã xử lý không tính kỳ lần nữa dù có event_id mới.
- Cùng event_id và payload đã normalize thì duplicate skipped. Cùng ID khác payload báo EVENT_ID_CONFLICT. Receipts sống trong cùng adapter instance. T3 cần receipts bền vững.
- Audit có previous/new, action/event, plan_id, event_id, timestamp và rule_type.

## OPEN ITEMS

COD recurring, retry email, thời điểm áp dụng cycle change sau reminder và chi tiết Resume lịch quá hạn vẫn OPEN.

## SANDBOX ASSUMPTIONS / GUARDS

- SANDBOX_ASSUMPTION — thay frequency_days giữ current expected date, log CYCLE_CHANGED_WITH_CURRENT_SCHEDULE_PRESERVED. Completion tiếp theo dùng frequency_days đang lưu. Đây là cách thử tạm, không chốt thời điểm áp dụng cycle chính thức.
- OPEN_RULE_GUARD — Resume chỉ khi expected_date > now_date. Nếu bằng hoặc đã qua ngày hiện tại, trả NEW_EXPECTED_DATE_REQUIRED; Postpone sang ngày tương lai rồi Resume. Đây là guard tạm. Cancelled luôn reject bằng PLAN_CANCELLED theo rule đã chốt.
- SANDBOX_ASSUMPTION — các fixture cũ không có event_id được adapter normalize với ID hash của payload, customer lấy từ plan, is_first_order từ so sánh order reference và period canonical. PaymentConfirmed cũ thiếu ngày dùng 1970-01-01 cho tracing. Fixture contract mới dùng đủ envelope. Nhánh legacy chỉ phục vụ regression 4.2–4.3.
- SANDBOX_ASSUMPTION — period_id opaque trong event OrderPlaced được fixture liên kết với cycle hiện tại. Việc tra nguồn T1-NL thật và xác minh mapping authoritative thuộc T3.
- Dedupe chỉ trong memory. Reminder chạy đúng ngày T-2, chưa gửi bù T-1. Khóa xác nhận giả được caller cung cấp mỗi lần chạy. Các ca PASS mang nhãn guard/assumption chỉ xác nhận implementation sandbox.

## Điểm nối T3-LO

Nguồn T2-NL thật sẽ thay nguồn fixture đi vào FakeOrderEventAdapter::handle. Adapter thật phải xác minh customer/order/period mapping, timestamp và nguồn event trước khi gọi nghiệp vụ, rồi cung cấp persistence và event receipts an toàn với nhiều worker. Sản phẩm thật nối qua product_id, variation_id, SKU, eligibility, price, stock. Các mã P001/P002 và O-1001/O-1002 hiện chỉ là simulation data.

Chưa có Woo hooks, UI production, payment, email thật, WP Cron hay Action Scheduler job. Bộ fixture management dùng M/L/XL. Bộ cũ giữ SIZE-M để regression. Runner reminder dùng thao tác cancel thật của sandbox thay cho ép trạng thái qua Reflection.
