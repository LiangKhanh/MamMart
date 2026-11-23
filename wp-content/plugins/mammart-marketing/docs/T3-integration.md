# Điểm nối thật dành cho T3-PH

T2-PH hiện là SIMULATION ONLY. Không có adapter WooCommerce tự kích hoạt.

## Nguồn dữ liệu và quyền sở hữu

| Nguồn | Bộ nối cần làm ở T3 | Điều kiện xác nhận |
|---|---|---|
| YL | Ánh xạ `baby_id` ↔ `child_id`; đọc chủ, DOB đã duyệt và version | Tên trường thật, quyền đọc, xử lý xóa/chuyển chủ và hồ sơ trùng |
| Consent | Một nguồn chuẩn cấp tài khoản; cung cấp phiên bản/thời điểm | Không tự bật theo lưu hồ sơ; trạng thái hiện tại thắng snapshot event |
| KH | Ánh xạ SKU/product/variation và whitelist đồ dùng ăn dặm | SKU chính thức; cờ và cách kế thừa biến thể; loại trừ thực phẩm |
| NL/giỏ | Đọc dòng hàng và phí từ server | Không tin giá, eligibility hoặc customer ID từ request của khách |
| Woo coupon | Tạo/tra qua `WC_Coupon`; metadata tham chiếu issue | Kiểu percent=15, không free shipping, chủ mã, hạn, giới hạn và quy tắc một mã |
| Woo order | Đọc qua `WC_Order`/CRUD, tương thích HPOS | Không mặc định order nằm trong posts/postmeta |
| NL/sự kiện | Chuẩn hóa `order_id`, `coupon_id`, event ID và trạng thái tiền/xử lý | Bảng giữ/trừ/trả lượt được leader và NL chốt trước khi nối |
| Email | Thay FakeMailer bằng adapter có kiểm consent, retry/log | Provider, giới hạn gửi lại, ý nghĩa SENT và chính sách log |
| Scheduler | Clock thật và bộ xét theo lịch | Timezone, giờ chạy, job chậm/bỏ lỡ và chống lặp |

## Hướng tích hợp coupon

Source local có `woocommerce_coupon_is_valid` và `woocommerce_coupon_is_valid_for_product`. Đây chỉ là điểm mở rộng dự kiến, chưa được hook bởi plugin này. Cần kiểm Classic Checkout lẫn Checkout Block/Store API thực tế.

Không dùng allowed email thay thế kiểm `customer_id`. Chỉ áp kiểm tra riêng cho coupon có metadata thuộc campaign PH; không thay đổi coupon của module khác.

`individual_use` trong source hiện tại có thể gỡ mã cũ khi thêm mã mới. Để đạt yêu cầu từ chối mã thứ hai theo thiết kế, kiểm cả hai thứ tự áp mã và không tự loại mã đang có mà không báo.

Không ánh xạ `SimulatedRedemptionSucceeded` trực tiếp sang Paid/Processing/Completed. Woo có xử lý lượt dùng riêng; nếu PH cũng trừ/hoàn thì có nguy cơ đếm hai lần. Bảng sự kiện chính thức phải chỉ rõ một bên sở hữu việc ghi lượt, giữ chỗ khi hai checkout cạnh tranh, hủy trước trả tiền và hoàn một phần/toàn bộ.

## Lưu quyền lợi thật

Đề xuất kho riêng với UNIQUE(campaign_id, baby_id), ledger sự kiện và outbox; không đưa `dob_version` vào unique key. Khi tạo coupon thật qua API và ghi issue ở hai nơi, cần cơ chế phục hồi/idempotency chống coupon mồ côi và cấp hai lần sau crash. Khóa JSON của bản thử không chứng minh được giao dịch phân tán hoặc HA.

## Điều kiện trước khi bật adapter thật

1. Nền KH dựng lại được, rõ phiên bản, timezone và dữ liệu chuẩn.
2. Chốt ngưỡng/trần/cơ sở tính, SKU, ngày biên, đăng ký muộn/bật lại consent, hạn sau sửa DOB, thuế/làm tròn/hàng sale.
3. Chốt bảng đơn → giữ/trừ/hoàn lượt với NL.
4. Kiểm quyền sở hữu với hai tài khoản, request trực tiếp, nonce, HPOS và hai loại checkout được sử dụng.
5. Thử bằng tài khoản/sản phẩm giả trong môi trường riêng; không chuyển tiền thật.

Các điều kiện chưa đạt phải ghi Bị vướng/PENDING_POLICY. Không mở mode thật chỉ bằng đổi `mode` trong config: constructor T2 cố ý từ chối mode khác `sandbox` và mailer khác FakeMailer.
