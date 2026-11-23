# T2-NL – Checkout, thanh toán và quản trị đơn (Mầm Mart)

- **Người làm:** Trần Hoàng Ngọc Lan (`T2-NL`)
- **Nhánh:** `feature/t2-nl-checkout`
- **Đầu vào dùng:** Gói Tuần 1 đã được leader chốt thứ Hai 09/11 (`T1-NL`, `T1-KH`). Không chờ code hay bản mới của người khác.

> **Quy ước trong README:** Mục nào ghi `[ĐIỀN]` là chỗ chỉ điền sau khi đã chạy thử trên máy. Mình chỉ ghi **"Đạt"** cho ca đã chạy thật; ca chưa chạy ghi **"Chưa chạy"**. Phần SePay được tách rõ thật và mô phỏng.

---

## 0. Tóm tắt cho leader (đọc mục này trước)

| Yêu cầu của thẻ T2-NL | Trạng thái | Nằm ở đâu |
| :--- | :--- | :--- |
| Phí giao 20.000đ (TP.HCM) / 30.000đ (nơi khác) | Có (cấu hình Woo + danh sách tỉnh/thành) | Mục 2, `mammart-provinces.php` |
| COD | Có | Mục 3 |
| SePay | Mô phỏng (cổng giả lập tự viết); SePay thật: `[ĐIỀN: chưa kết nối / đã kết nối]` | Mục 5 |
| Trạng thái, hủy, lý do hủy | Có | Mục 3, 4 |
| Giữ/trừ/hoàn tồn (không lặp) | Có (cờ chống lặp) | Mục 4 |
| SePay quá 24 giờ chưa trả thì hủy | Có, WP-Cron kiểm mỗi giờ. COD không tự hủy | Mục 4 |
| Sự kiện theo giao ước cho Tuần 3 | Có 4 sự kiện, chống phát lặp | Mục 7 |
| Bộ đơn mẫu `O-1001..O-1008` | Có (khác bộ Tuần 1 ở một số đơn, xem mục 6) | Mục 6 |
| Ca thử tiền/tồn | `[ĐIỀN KẾT QUẢ THẬT]` | Mục 9 |

---

## 1. Các file trong bài làm

**Thư mục:** `wp-content/plugins/mammart-provinces/` (plugin WordPress, bật trong **Plugins**).

| File | Làm gì |
| :--- | :--- |
| `mammart-provinces.php` | Plugin chính. Thêm danh sách 34 tỉnh/thành VN vào form checkout (WooCommerce không có sẵn), bắt buộc chọn tỉnh; nạp các file bên dưới. |
| `sepay-gateway-mock.php`, `sepay-block.js` | Cổng thanh toán SePay giả lập (tự viết, id `mammart_sepay`), hiển thị ở Block Checkout. Không phải plugin SePay chính thức. |
| `sepay-simulator.php` | Trang **WooCommerce → Mô phỏng SePay**: bắn webhook giả (đủ tiền, thiếu tiền, trùng mã GD, tiền về trễ sau khi hủy) và ghi log. |
| `order-workflow.php`, `sepay-expiry.php` | Trạng thái đơn, quy tắc tồn kho, khách và admin hủy kèm lý do, WP-Cron hủy đơn SePay quá hạn. |
| `contract-events-and-samples.php` | Phát 4 sự kiện chuẩn giao ước (chống lặp), tạo bộ đơn mẫu, xuất JSON/JSONL, trang **WooCommerce → Sự kiện & Đơn mẫu**. |

**Cài đặt:** Chép thư mục `mammart-provinces` vào `wp-content/plugins/`, bật plugin, vào **WooCommerce → Settings → Shipping** kiểm tra vùng giao hàng (`TP. Hồ Chí Minh` = Flat rate `20000`, vùng còn lại `Việt Nam` = Flat rate `30000`).

---

## 2. Phí giao và địa chỉ

- WooCommerce không có sẵn danh sách tỉnh/thành Việt Nam, nên plugin nạp **34 tỉnh/thành** (theo cách sáp nhập năm 2025). Đây là lựa chọn của mình, chờ leader xác nhận (điểm `Q10`).
- Hai vùng giao hàng: **"TP. Hồ Chí Minh"** (chỉ chọn tỉnh Hồ Chí Minh, Flat rate `20.000`) đặt trên **"Các tỉnh thành khác"** (cả Việt Nam, Flat rate `30.000`). Thứ tự này bắt buộc vì WooCommerce dùng vùng đầu tiên khớp.
- **Tổng đơn** = tiền hàng + phí giao (chưa có giảm giá trong tuần này, vì chưa ghép coupon).

---

## 3. Trạng thái đơn

| Trạng thái (hiển thị) | Slug Woo | Ý nghĩa |
| :--- | :--- | :--- |
| Chờ xác nhận (COD) | `cod-awaiting` | COD mới đặt. Đơn COD cũ có thể dùng `on-hold` |
| Chờ thanh toán (SePay) | `on-hold` | SePay mock mới đặt |
| Đã xác nhận / Chờ đóng gói | `confirmed` *(tùy chỉnh)* | COD đã được admin duyệt, hoặc SePay đã nhận đủ tiền. Khách vẫn tự hủy được |
| Đang đóng gói | `processing` | Khóa nút Hủy của khách |
| Đang giao hàng | `shipping` *(tùy chỉnh)* | Đã bàn giao vận chuyển |
| Hoàn thành | `completed` | Đã giao; với COD, ghi nhận đã thu tiền |
| Đã hủy | `cancelled` | Đơn đã hủy |
| Chờ hoàn tiền | `refund-pending` *(tùy chỉnh)* | Đơn đã trả tiền nhưng bị hủy hoặc hết hàng lúc tiền về |

- **Tách ba mốc:** `đặt đơn ≠ đã trả tiền ≠ đã giao`. Trạng thái tiền lưu riêng trong meta `_mammart_payment_status` (`Unpaid`, `Paid`, `Unpaid_Partial`, `Refund_Pending`).
- **Mốc "bắt đầu đóng gói" (giả định, chờ leader chốt `Q7`):** Hiện được hiểu là lúc admin chuyển đơn sang **"Đang đóng gói" (`processing`)**. Vì SePay báo đã trả tiền đưa đơn vào **"Chờ đóng gói" (`confirmed`)** chứ không vào `processing`, nên khách vẫn hủy được sau khi trả tiền. Chưa có trường thời điểm đóng gói riêng.

---

## 4. Quy tắc tồn kho (giả định, chờ leader chốt `Q2`)

- **Không trừ tồn khi tạo đơn** (để đơn SePay chưa trả không giữ hàng).
- **Trừ tồn đúng 1 lần khi:** SePay báo đủ tiền, hoặc admin duyệt đơn COD (chuyển sang `"Đã xác nhận"`).
  - Trước khi trừ, kiểm tồn thực tế từng sản phẩm.
  - **SePay đã trả tiền mà hết hàng:** Không trừ, chuyển thẳng `"Chờ hoàn tiền"`.
  - **Duyệt COD mà hết hàng:** Chặn duyệt, đơn quay lại `"Chờ xác nhận"`, admin thấy cảnh báo.
- **Hoàn tồn đúng 1 lần** khi đơn bị hủy hoặc chuyển `"Chờ hoàn tiền"`, chỉ nếu trước đó đã trừ.
- **Cờ chống lặp:** `_mammart_stock_deducted` (đã trừ) và `_mammart_stock_restocked` (đã hoàn). Ô quản trị bên phải trang sửa đơn hiển thị hai cờ này.
- Không dùng bảng giữ tồn 15 phút (`HOLD_INVENTORY`).
- **COD mới đặt vào "Chờ xác nhận" (`cod-awaiting`):** Admin duyệt sang `confirmed` trước khi bắt đầu đóng gói. Nếu thiếu tồn, đơn vẫn chờ xác nhận.
- **Tự hủy sau 24 giờ** áp dụng cho SePay mock còn `pending` hoặc `on-hold` với meta `Unpaid` hoặc `Unpaid_Partial`. Hook `mammart_expire_sepay_orders` chạy mỗi giờ qua WP-Cron và bỏ qua COD, đơn mẫu, đơn đã có ngày thanh toán hoặc meta không xác định. Đơn chuyển thiếu vẫn giữ cờ đối soát. Lý do hủy là `"Quá hạn thanh toán SePay 24 giờ"`, người hủy là `system`.

### Hủy đơn

- **Khách tự hủy:** Ở trang chi tiết đơn (trong tài khoản), khi đơn ở `pending`, `on-hold`, `cod-awaiting` hoặc `confirmed`. Bắt buộc chọn lý do (5 lựa chọn). Kiểm tra đúng chủ đơn và nonce.
- **Admin hủy:** Ô **"Mầm Mart: Kiểm Soát Tồn Kho, Hủy & Đối Soát"** có tích chọn hủy và nhập lý do.
- Lý do lưu ở `_mammart_cancel_reason`, người hủy ở `_mammart_cancelled_by` (`customer` / `admin` / `system`).
- **Đơn đã trả tiền bị hủy:** Chuyển `"Chờ hoàn tiền"`, ghi số tiền hoàn bằng 100% tổng đơn gồm phí giao (`_mammart_refund_amount`, giả định `Q8`). Hoàn tiền thực hiện thủ công ngoài web.

---

## 5. Thanh toán: thật và mô phỏng (log)

| Phương thức | Thật hay mô phỏng | Ghi chú |
| :--- | :--- | :--- |
| **COD** | Thật trên website thử | `[ĐIỀN: ngày thử, kết quả]` |
| **SePay** (cổng `mammart_sepay`) | Mô phỏng | Cổng giả lập tự viết, tạo đơn `on-hold`, nội dung chuyển khoản `MM<mã đơn>` |
| **SePay chính thức** (plugin `sepay-gateway`) | `[ĐIỀN: chưa kết nối / đã kết nối vào ngày ...]` | Website chạy localhost nên SePay không gọi webhook vào được; không chuyển tiền thật để thử |

- **Mô phỏng webhook:** Trang **WooCommerce → Mô phỏng SePay** có 4 ca: đủ tiền, thiếu tiền, trùng mã giao dịch, tiền về sau khi đơn đã hủy. Mỗi dòng log ghi nhãn `[MÔ PHỎNG]` hoặc `[THẬT]`.
- **File log:** `wp-content/uploads/mammart-sepay-webhook.log`.
- **Trùng mã giao dịch:** Lưu mã đã xử lý, lần sau bỏ qua (`IGNORED_DUPLICATE`).
- **Sai số tiền:** Không đổi đơn, gắn cờ đối soát thủ công (`_mammart_manual_reconcile`).
- **Tiền về sau khi đơn đã hủy:** Không mở lại đơn, gắn cờ đối soát và số tiền hoàn.

---

## 6. Bộ đơn mẫu `O-1001` → `O-1008`

Tạo bằng nút **"Tạo / Reset bộ 8 đơn mẫu"** ở **WooCommerce → Sự kiện & Đơn mẫu**. Đây là dữ liệu mẫu, không phải đơn khách thật. Họ tên, số điện thoại, email, địa chỉ đều là giá trị giả.

| Mã | Phương thức | Trạng thái | Tiền |
| :--- | :--- | :--- | :--- |
| `O-1001` | COD, TP.HCM | Chờ xác nhận | Chưa thu |
| `O-1002` | COD, Hà Nội | Đã xác nhận / chờ đóng gói | Chưa thu |
| `O-1003` | SePay, TP.HCM | Chờ thanh toán | Chưa trả |
| `O-1004` | SePay, Đà Nẵng | Chờ đóng gói | Đã trả |
| `O-1005` | SePay, TP.HCM | Đã xác nhận / Chờ đóng gói | Đã trả |
| `O-1006` | COD, TP.HCM | Hoàn thành (đã giao) | **COD đã thu** |
| `O-1007` | SePay, Cần Thơ | Chờ hoàn tiền (khách hủy) | Đã trả, chờ hoàn |
| `O-1008` | COD, Đồng Nai | Đang giao | **COD chưa thu** |

**Khác với bộ mẫu Tuần 1 (cần nói rõ để mọi người dùng cùng phiên bản):**
- Giữ đúng yêu cầu: `O-1006` (đã giao + COD đã thu), `O-1008` (đang giao + COD chưa thu).
- Đã đổi: `O-1002` đến `O-1005` và `O-1007` mô tả tình huống khác bộ Tuần 1 (ví dụ Tuần 1 có đơn đã hủy `O-1004`, đơn đã hoàn `O-1005`, đơn có mã giảm `O-1007`; bộ mới không có).
- Số tiền do WooCommerce tính từ sản phẩm thật trong cửa hàng (mỗi đơn 1 sản phẩm), không trùng số tiền ở bảng Tuần 1.
- `[ĐIỀN: leader/nhóm xác nhận dùng bộ nào làm bộ chung]`

---

## 7. Sự kiện theo giao ước (cho Tuần 3)

Bốn sự kiện: `OrderPlaced`, `PaymentConfirmed`, `OrderCompleted`, `OrderCancelled`.

| Sự kiện | Phát khi |
| :--- | :--- |
| `OrderPlaced` | Tạo đơn mới |
| `PaymentConfirmed` | SePay xác nhận đủ tiền (đơn sang Chờ đóng gói), hoặc COD giao xong và ghi đã thu |
| `OrderCompleted` | Đơn Hoàn thành |
| `OrderCancelled` | Đơn bị hủy hoặc chuyển Chờ hoàn tiền |

- **Chống phát lặp:** Khóa `idempotency_key = <tên sự kiện>:<mã đơn>`. Cùng khóa thì không phát lần hai và ghi `IGNORED_DUPLICATE_EVENT` vào log. Đây là điều Long cần để không tính kỳ nhắc hai lần.
- **Giao diện dữ liệu mỗi sự kiện:** Có `event_id` (dạng `EVT-XXXXXXXXXXXX`), `occurred_at` (ISO-8601), `order_code`, `customer_id`, trạng thái (`order_status`, `delivery_status`), phương thức và trạng thái thanh toán (`payment_method`, `payment_status`, `cod_collected`), `sepay_reference`, `shipping_fee`, `total_amount`, `cancel_reason`, `cancelled_by`, danh sách dòng hàng `items` (`product_id`, `sku`, `name`, `quantity`, `unit_price`, `line_total`).
- Tên trường là quy ước thử độc lập, không cam kết trùng tên hook/trường thật của WooCommerce (đúng theo thẻ bàn giao mục 3).
- **Xuất cho Long, Phúc, Liêm:** `wp-content/uploads/mammart-contract-events.json` và `.jsonl`. Nút **"Test chống lặp sự kiện"** bắn lặp `O-1006` để kiểm tra số sự kiện không tăng.
- `payment_method` trong sự kiện được chuẩn hóa về `cod` hoặc `sepay` (đơn thật dùng cổng `mammart_sepay` cũng ra `sepay`).
- Tên hook và trường thật của WooCommerce vẫn cần đối chiếu khi ghép với module khác.

---

## 8. Chưa làm / hạn chế (nói thật)

1. SePay thật chưa tích hợp. Ca tiền về chạy bằng công cụ mô phỏng trong trang quản trị WooCommerce.
2. Hai endpoint đọc `mammart/v1/contract-events` và `mammart/v1/sample-orders` đã giới hạn chỉ tài khoản quản trị (vì có tên, số điện thoại, email). Hệ quả: Long, Phúc, Liêm nên đọc qua file JSON/JSONL đã xuất (mục 7), không gọi trực tiếp khi chưa đăng nhập admin.
3. Tự hủy 24 giờ chỉ áp dụng cho cổng SePay giả lập (`mammart_sepay`). Khi có plugin SePay chính thức, cần đối chiếu mã cổng thanh toán của plugin đó.
4. Chưa có bảng `REFUND_LOG`; chưa ghi nhận mã giao dịch hoàn tiền. Admin dùng trạng thái có sẵn của WooCommerce để đánh dấu đã hoàn.
5. Chưa có trường thời điểm bắt đầu đóng gói; mốc khóa hủy dựa vào trạng thái `processing` (giả định).
6. Admin hủy không bị bắt buộc nhập lý do (để trống thì ghi lý do mặc định); khách hủy thì bắt buộc.
7. Các giả định ở mục 2, 3, 4 chưa được leader xác nhận (điểm mở `Q2`, `Q5`, `Q7`, `Q8`, `Q10`).
8. Code chưa được kiểm thử tự động; chỉ chạy thử tay trên máy cá nhân.

---

## 9. Ca thử tiền / tồn

> Ghi **"Đạt"** chỉ khi đã chạy thật. Ảnh chụp lưu ở `[ĐIỀN: đường dẫn]`.

| Ca | Nội dung | Kết quả mong đợi | Kết quả thật |
| :--- | :--- | :--- | :--- |
| `S01` | COD tại TP.HCM | Phí 20.000, tổng = hàng + 20.000, đơn Chờ xác nhận | `[ĐIỀN]` |
| `S02` | Đơn tỉnh khác | Phí 30.000 | `[ĐIỀN]` |
| `S03` | SePay đủ tiền (mô phỏng) | `Paid`, đơn Chờ đóng gói, trừ tồn 1 lần | `[ĐIỀN]` |
| `S06` | SePay trả tiền nhưng hết hàng | Chờ hoàn tiền, không trừ tồn | `[ĐIỀN]` |
| `S07` | Admin duyệt COD | Kiểm tồn, trừ 1 lần; hết hàng thì chặn | `[ĐIỀN]` |
| `S08` | COD giao xong | Hoàn thành, COD ghi đã thu | `[ĐIỀN]` |
| `S09` | Khách hủy khi chưa đóng gói | Đã hủy, có lý do, hoàn tồn nếu đã trừ | `[ĐIỀN]` |
| `S10` | Đơn đang đóng gói | Khách không thấy nút hủy | `[ĐIỀN]` |
| `S11` | Khách hủy đơn SePay đã trả | Chờ hoàn tiền, ghi 100% tổng | `[ĐIỀN]` |
| `S13` | Tiền về sau khi đơn đã hủy (mô phỏng) | Giữ Đã hủy, gắn cờ đối soát | `[ĐIỀN]` |
| `S14` | Thiếu tiền / trùng mã GD (mô phỏng) | Không đổi đơn / bỏ qua | `[ĐIỀN]` |
| Chống lặp tồn | Hủy đơn hai lần | Tồn không tăng lần hai | `[ĐIỀN]` |
| Chống lặp sự kiện | Nút test bắn lặp `O-1006` | Số sự kiện không tăng | `[ĐIỀN]` |
| `S05` | SePay quá 24 giờ chưa trả (đơn và đồng hồ thử trên database riêng) | Đã hủy, người hủy `system`, không cần hoàn tồn vì chưa trừ | `[ĐIỀN]` |
| COD không tự hủy | Chạy job với đơn COD chờ xác nhận | Đơn COD giữ nguyên | `[ĐIỀN]` |

---

## 10. Phụ thuộc và cách dùng độc lập

- Dùng giỏ WooCommerce tiêu chuẩn và sản phẩm có sẵn; không phụ thuộc giao diện của Ái Linh, nhắc mua hay coupon mới.
- **Phúc** dùng được các trường đơn/giá/phí trong sự kiện; chưa ghép coupon thật.
- **Liêm** đọc được đơn qua trường `payment_status` và `order_status` tách riêng.
