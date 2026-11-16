# T2-AL — Danh sách điểm nối (chưa tích hợp thật)

Phạm vi AL: trang chủ, danh mục, chi tiết, giỏ, khung tài khoản. Mọi chỗ nối bên dưới **chưa có bản thật của người khác** — chỉ chừa chỗ.

| Mã | Nối với | Vị trí trong giao diện | Trạng thái hiện tại | Cần từ người kia |
|---|---|---|---|---|
| N-01 | T2-NL checkout | Nút "Tiến hành thanh toán" ở giỏ → `/thanh-toan/` | Dùng trang thanh toán mặc định của WooCommerce (chưa Việt hóa, chưa có phí 20k/30k, COD/SePay). Khách chưa đăng nhập bị chuyển sang `/tai-khoan/?redirect_to=…/thanh-toan/` và quay lại sau khi đăng nhập. | Lan xác nhận đọc giỏ Woo chuẩn: `customer_id`, dòng (`product_id`, `variation_id`, `sku`, `quantity`, `unit_price`), `subtotal`, `cart_hash` (= `WC()->cart->get_cart_hash()`). AL không tính phí/tổng cuối/trừ tồn. |
| N-02 | T2-YL Stage | 3 thẻ "Mua theo giai đoạn" ở trang chủ; ghi chú trong cột lọc ở danh mục | Thẻ vô hiệu, gắn nhãn "Sắp có"; chưa đọc tham số tuổi. | YL cung cấp URL/tham số lọc và nhãn tuổi theo SKU. |
| N-03 | T2-YL hồ sơ bé | Tài khoản → "Hồ sơ của bé" (`/tai-khoan/mm-baby/`) | Trang giữ chỗ, ghi rõ T2-YL phụ trách. | Form/nội dung endpoint `mm-baby`. |
| N-04 | T2-LO nhắc mua | Tài khoản → "Kế hoạch nhắc mua" (`mm-plans`); dòng giỏ từ nhắc mua | Trang giữ chỗ. Giỏ **chưa** hiển thị nhãn nguồn/`confirmation_id` (không có dữ liệu để thử). | Long cung cấp endpoint `mm-plans` và cách đưa dòng từ nhắc mua vào giỏ Woo (giá hiện tại, cùng luật tồn). |
| N-05 | T2-PH ưu đãi | Tài khoản → "Ưu đãi của tôi" (`mm-offers`); ô "Thêm mã giảm giá" trong giỏ | Giữ chỗ; ô mã giảm giá là của WooCommerce, chưa có quy tắc PH. | Phúc cung cấp endpoint `mm-offers` và quy tắc kiểm mã. |
| N-06 | T2-LI xem đơn | Tài khoản → "Đơn hàng của tôi" (`orders`) | AL **không** thay nội dung; đang là trang đơn mặc định của Woo ("Bạn chưa có đơn hàng nào"). | Liêm thay nội dung endpoint `orders`. |
| N-07 | T2-KH dữ liệu | Thẻ sản phẩm / chi tiết | Chưa hiển thị nhãn tuổi (`age_label`) vì chưa có dữ liệu nguồn. Đang dùng bộ sản phẩm thử riêng (`Docs/T2-AL/seed_test_products.php`). | Khánh bàn giao 30 sản phẩm mẹ và bé có SKU/tồn/biến thể + nhãn tuổi có nguồn. |

## Việc nằm ngoài AL nhưng cần lưu ý
- Giỏ **không giữ tồn** (BR-AL-06). Mốc giữ/trừ tồn khi tạo đơn là của T2-NL; cấu hình "giữ tồn đơn chờ thanh toán" của WooCommerce (`woocommerce_hold_stock_minutes`) chưa được nhóm chốt.
- Cách WooCommerce gộp giỏ khách vào giỏ tài khoản chỉ được **quan sát** (TC-AL-20, 20b), chưa phải quy tắc nhóm.
