# Mầm Mart Recurring Reminder Sandbox

T2 simulation only. Plugin thử độc lập bằng dữ liệu và sự kiện giả. Mã C001/P001/P002, mã variation và mã đơn trong fixture chỉ phục vụ mô phỏng. Tích hợp checkout/order WooCommerce thật dành cho T3-LO.

## Đã hoàn thành đến bước 4.4

- Plan core, eligibility theo fixture, quantity dương, cycle 21/28/35 (mặc định 28).
- Fake OrderPlaced, OrderCompleted, OrderCancelled theo envelope T1-NL và dedupe event_id.
- Reminder T-2, dedupe theo plan/expected date, FakeMailer SENT/FAILED và scheduler nhận thời gian giả.
- Quản lý quantity, variation, cycle, postpone, pause, resume và cancel bằng service.
- Fixture, test CLI, audit log, báo cáo JSON/Markdown. PLAN-11 đã có kiểm thử thực thi.

## Chạy thử

Yêu cầu PHP 8.1 trở lên. Từ thư mục plugin chạy lần lượt:

```powershell
C:\wamp64\bin\php\php8.1.0\php.exe bin/test-plan.php
C:\wamp64\bin\php\php8.1.0\php.exe bin/test-reminder.php
C:\wamp64\bin\php\php8.1.0\php.exe bin/test-management.php
```

Runner cuối chạy cả bộ MGMT và EVT. Kết quả được ghi vào `reports/`. Core và test chạy bằng bộ nhớ, độc lập với WordPress/MySQL.

## CHỐT TỪ TUẦN 1

- Customer chủ động đăng ký sản phẩm tã/khăn ướt được đánh dấu `_is_recurring_eligible = 1`. Plan mới PendingActivation.
- Đơn đầu Completed mới kích hoạt. Đơn mua lại Completed tính kỳ mới từ ngày hoàn tất cộng chu kỳ. Trạng thái chờ trả tiền/xử lý/giao chưa mở kỳ mới.
- Reminder trước 2 ngày để customer quay lại kiểm giá/tồn và chủ động đặt hàng. Module không tự đặt hàng, thu tiền, hủy đơn đã tồn tại, thay sản phẩm hết hàng hoặc gửi SMS.
- Qua expected date mà chưa có phản hồi/đơn mới thì bỏ kỳ và pause. Đơn recurring bị hủy thì pause.
- Customer được đổi size/quantity/cycle, dời lịch, pause, resume, cancel. Cancelled là trạng thái kết thúc, muốn dùng lại phải tạo plan mới.
- Giá reminder chỉ tham khảo. Giá thật lấy tại checkout ở T3.

## OPEN ITEMS

- COD recurring.
- Email retry policy.
- Thời điểm áp dụng cycle mới sau khi reminder đã gửi.
- Chi tiết Resume khi schedule cũ đã hết hạn.

## SANDBOX ASSUMPTIONS / GUARDS

- `SANDBOX_ASSUMPTION` — thay frequency_days giữ current expected date. Thời điểm áp dụng cycle mới vẫn OPEN; sandbox giữ current expected date để không tự sửa một kỳ đang chạy.
- `OPEN_RULE_GUARD` — Resume chỉ cho lịch còn ở ngày tương lai theo UTC. Lịch quá hạn hoặc đúng ngày hiện tại cần chọn ngày tương lai bằng Postpone rồi Resume. Đây là guard tạm, không phải quy định business cuối.
- Dedupe event/reminder chỉ trong cùng sandbox run. Legacy fixture 4.2–4.3 được normalize như mô tả trong docs. Period opaque được OrderPlaced liên kết với cycle hiện tại để chạy thử.
- PASS của các ca có nhãn OPEN_RULE_GUARD/SANDBOX_ASSUMPTION chỉ chứng minh guard/giả định chạy đúng.

## Phần dành cho T3

Lưu trữ thật, Woo order hooks, checkout, payment, email/SMTP, cron/Action Scheduler job, giá/tồn kho thật, authorization và UI khách hàng sẽ được làm ở T3. Trong T2, code chỉ đăng ký class khi plugin load. Các nghiệp vụ chạy khi runner/service được gọi rõ ràng.

Xem [contract và điểm nối](docs/sandbox-contract.md).
