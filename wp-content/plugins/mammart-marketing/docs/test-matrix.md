# T2-PH — bộ 30 ca kiểm

SIMULATION ONLY. Đây là đặc tả ca kiểm; kết quả thực thi nằm riêng ở `reports/test-results.md` và JSON. Mỗi ca dùng kho mới, trừ khi ca đó chủ động chạy lại trên cùng kho để kiểm idempotency.

| ID | Thiết lập / hành động | Kết quả mong đợi |
|---|---|---|
| T01 | Bỏ bản ghi consent, giữ profile; bật/tắt consent | Mặc định NO_CONSENT; lịch sử/version thay đổi; DOB không đổi |
| T02 | DOB 06/05, clock 05/11 23:59:59 | NOT_DUE |
| T03 | DOB 06/05, clock 06/11 | Một issue 15%; hết hạn exclusive 01/12 00:00 |
| T04 | Đăng ký 20/11 sau mốc 15/11 | Chưa chốt clock thì PENDING_POLICY; trial ngày lịch chờ đến 21/11 |
| T05 | Đăng ký 30/11, ngày xét 01/12 | Không cấp bù |
| T06 | Cấp cùng bé sáu lần | Cùng mã; chỉ một issue |
| T07 | Hai PHP process cấp cùng bé/campaign trên cùng kho | Một ISSUED và một ALREADY_ISSUED; cùng mã |
| T08 | Hai bé cùng tài khoản | Hai quyền lợi riêng |
| T09 | DOB thiếu/sai/tương lai/chưa duyệt | INVALID_DOB |
| T10 | Sự kiện duyệt sửa DOB trước cấp | Không cấp theo mốc cũ; đến mốc mới mới cấp |
| T11 | Sửa DOB sau cấp | Không cấp thêm; hạn cũ không bị viết lại; báo policy còn mở |
| T12 | Event DOB lặp/version cũ/sai chủ | Không ghi đè ngày mới, không cấp thêm |
| T13 | Ngày 29–31 thiếu ngày trong tháng đích/năm nhuận | PENDING_POLICY |
| T14 | Rút consent trước xét | Không cấp |
| T15 | Rút consent sau cấp | Mã cũ vẫn hợp lệ; chặn gửi |
| T16 | Mail giả lỗi rồi retry | Mã và coupon ID giữ nguyên; log FAILED rồi SIMULATED_SENT |
| T17 | Mail lỗi rồi opt-out | Retry bị chặn bởi consent hiện tại |
| T18 | Khách chưa đăng nhập/tài khoản khác | WRONG_OWNER |
| T19 | Cuối tháng 23:59:59 và đầu tháng 00:00:00 | Còn hạn rồi EXPIRED; chặn gửi hết hạn |
| T20 | Giỏ chỉ hàng hợp lệ; giả mạo giá/cờ trong dòng giỏ | Dùng giá/cờ từ catalog; giảm đúng 15% |
| T21 | Giỏ hỗn hợp + ship 20k/30k | Chỉ giảm hàng hợp lệ; giữ ship |
| T22 | Giỏ thực phẩm hoặc hàng khác | NO_ELIGIBLE_ITEMS |
| T23 | Biến thể không rõ eligibility | PENDING_POLICY; không suy từ cha |
| T24 | Áp hai mã theo hai thứ tự; hai coupon vào cùng đơn | ONE_COUPON_PER_ORDER |
| T25 | Ghi thành công giả rồi thử lại | SIMULATED_USED rồi ALREADY_USED |
| T26 | Gửi lặp event dùng thành công | DUPLICATE_EVENT; không tiêu thêm lượt |
| T27 | Pending/Paid/Completed/Cancelled/PartialRefund/Refund | PENDING_POLICY; không tự trừ/hoàn |
| T28 | Policy chưa chốt; thử ngưỡng/trần/làm tròn có nhãn | Chặn mặc định; tính đúng chỉ trong config trial rõ ràng |
| T29 | Lưu issue trước send, tạo service mới; lỗi transaction; giỏ lỗi | Không tạo thêm mã; rollback không mất issue; dữ liệu sai bị chặn |
| T30 | WordPress thật: khách/admin, nonce thiếu/sai/đúng, render | Chặn thiếu quyền/nonce trước ghi; admin hợp lệ thấy dữ liệu được escape |

T30 cần web runtime WordPress và phiên đăng nhập thực; runner CLI không thay thế bằng mock rồi đánh dấu PASS. Các ca PENDING_POLICY chỉ kiểm module dừng đúng, không xác nhận chính sách kinh doanh.
