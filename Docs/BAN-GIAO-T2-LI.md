# Bàn giao T2-LI – Xem đơn hàng trong My Account

## Cài đặt

Chép `wp-content/mu-plugins/mammart-account-orders.php` vào đúng đường dẫn đó dưới thư mục WordPress. Tạo thư mục `mu-plugins` nếu máy đích chưa có. WordPress tự nạp file PHP này, không cần kích hoạt trong trang Plugins. Bản đã kiểm tra dùng WooCommerce 11.2.1 và theme Twenty Twenty-Five.

Đăng nhập bằng tài khoản Customer, mở **Tài khoản của tôi → Đơn hàng của tôi → Xem chi tiết**. Nếu WooCommerce đang để **Sắp ra mắt**, dùng **WooCommerce → Settings → Site visibility → Private link** trong từng phiên thử, kể cả phiên chưa đăng nhập.

## Chuẩn bị dữ liệu trên máy khác

1. Xác nhận đúng website và database local trước khi tạo dữ liệu. Tạo hoặc dùng lại hai tài khoản Customer riêng A và B với email giả thuộc `example.invalid`. Đặt mật khẩu thử trong **Users → All Users** trên máy đó.
2. Dùng API CRUD `WC_Order` qua PHP CLI hoặc WP-CLI trên bản local. Tìm đơn thử đã có theo thẻ bộ dữ liệu và khách trước khi tạo. Gắn đúng khách bằng `set_customer_id()`, chọn sản phẩm demo có sẵn, thêm dòng hàng có số lượng và giá, thêm một dòng phí giao, dùng giảm giá 0 và tính tổng bằng `calculate_totals(false)` cho bộ mẫu không thuế. Ghi lại mã đơn WooCommerce trả về.
3. Tạo các trường hợp theo bảng dưới. Với đơn SePay giả lập, ghi nhận thời điểm đã thanh toán bằng `set_date_paid()`. Thực hiện trong tiến trình offline, chặn email bằng `pre_wp_mail`, chặn HTTP qua `pre_http_request` và tắt webhook WooCommerce trong tiến trình tạo. Trả về false từ `woocommerce_payment_complete_reduce_order_stock` và `woocommerce_can_reduce_order_stock` trong tiến trình đó, rồi kiểm tra tồn kho trước/sau. Dùng cơ chế này để tránh gửi thư, gọi cổng thanh toán hoặc trừ tồn khi tạo. Không gọi `payment_complete()` hay chạy luồng đặt hàng thật.

| Khách | Ví dụ trên máy Liêm | Phương thức | Trạng thái WooCommerce | Dữ liệu thanh toán |
|---|---|---|---|---|
| A | #61 | COD | `on-hold` | Chưa có ngày thanh toán |
| A | #62 | SePay giả lập | `processing` | Có ngày thanh toán giả lập |
| B | #63 | COD | `on-hold` | Chưa có ngày thanh toán |

Dùng mã đơn do WooCommerce tạo trên máy đích. `processing` ở đây là trạng thái WooCommerce, còn mốc đóng gói cần dữ liệu nghiệp vụ riêng. Màn hình hiện **Chưa có dữ liệu xử lý** trong khi chờ nguồn dữ liệu từ Ngọc Lan. SePay chỉ là phương thức giả lập, không có giao dịch.

## Kết quả Bước 3

| Ca | Cách kiểm tra | Kết quả |
|---|---|---|
| Giới hạn danh sách theo khách và kiểm quyền trước khi render | Đọc mã WooCommerce và file T2-LI | Đạt. WooCommerce lọc theo khách hiện tại và kiểm tra quyền `view_order` phía máy chủ. |
| A xem danh sách và mở #61, #62 | WordPress CLI, tài khoản A | Đạt. Danh sách chỉ có #61, #62, mở được cả hai. |
| B xem danh sách và mở #63 | WordPress CLI, tài khoản B | Đạt. Danh sách chỉ có #63, mở được #63. |
| A mở #63, B mở #61 và #62 | WordPress CLI, ngữ cảnh từng khách | Đạt. Cả ba yêu cầu bị từ chối. HTML kiểm tra không chứa tóm tắt, bảng chi tiết hoặc email thanh toán của đơn. |
| Khách chưa đăng nhập mở #61, #62 và #63 | WordPress CLI, ngữ cảnh khách | Đạt. Hiện form đăng nhập và không hiện chi tiết đơn. |
| Trạng thái và tổng tiền | WordPress CLI | Đạt. #61 chưa thanh toán, 41 ₫. #62 đã thanh toán giả lập, 25 ₫. Trạng thái xử lý nằm riêng. |
| Lặp lại các ca bằng HTTP trên WAMP | Phiên HTTP riêng A, B và khách chưa đăng nhập | Chưa xác nhận. Các URL trả màn hình Sắp ra mắt. Chưa kiểm tra trình duyệt. |

Khi có Private link, lặp lại các ca qua trình duyệt bằng ba phiên riêng. A và B mở đơn của mình từ danh sách, sau đó thử mở URL `/my-account/view-order/<mã-đơn>/` của khách kia. Phiên chưa đăng nhập mở cả ba URL. Chờ xác nhận đầy đủ các ca này trước khi commit và push.
