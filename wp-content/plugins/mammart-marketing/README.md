# Mầm Mart — T2-PH marketing sandbox

**SIMULATION ONLY — module thử độc lập theo giao ước 10/11/2026.**

Tác giả task: Ngụy Trọng Phúc. Hạn T2-PH: 15/11/2026 23:59, giờ Việt Nam.

Plugin không tạo `WC_Coupon`, không nối checkout, không tạo đơn Woo, không gửi email, không thanh toán, không đăng ký cron. Không đọc/ghi bảng bé của YL, bảng đơn của NL hoặc SQL chung. Các mã bắt đầu `DEMO-` chỉ tồn tại trong kho JSON giả, không sử dụng được tại Woo checkout.

## 1. Chạy độc lập bằng PHP CLI

Yêu cầu: PHP **8.0 trở lên**, JSON, quyền ghi thư mục tạm của hệ điều hành và `reports/`. Không cần Composer, database, WordPress hay thư viện bổ sung. Test đồng thời T07 cần `proc_open` và quyền chạy tiến trình PHP con.

Từ `D:\T2-PH` trong PowerShell:

```powershell
php --version
php wp-content/plugins/mammart-marketing/tests/run.php
php wp-content/plugins/mammart-marketing/bin/demo.php
```

Nếu PHP không nằm trong PATH nhưng có XAMPP như môi trường đã kiểm tra:

```powershell
& 'D:\XamPP\php\php.exe' wp-content/plugins/mammart-marketing/tests/run.php
& 'D:\XamPP\php\php.exe' wp-content/plugins/mammart-marketing/bin/demo.php
```

Runner tạo dữ liệu thử mới trong thư mục tạm với tên ngẫu nhiên cho mỗi lần chạy; không sửa fixtures. Kết quả lần chạy gần nhất được ghi đè trong `reports/`:

- `test-results.json`: từng ca, PASS/FAIL/BLOCKED, môi trường và thời điểm thực thi UTC.
- `test-results.md`: bảng kết quả dễ đọc.
- `test-audit-log.json`: log consent/cấp/gửi/kiểm/dùng của các ca thử.
- `demo-log.json`: toàn bộ vòng demo, trạng thái giả và log.

Exit code của runner bằng 1 khi có FAIL; bằng 0 nếu không có FAIL. **Exit code 0 không đồng nghĩa toàn bộ ca PASS:** phải đọc số BLOCKED. Thời điểm trong log nghiệp vụ là đồng hồ giả; `executed_at_utc` mới là thời điểm thực thi test trên máy.

## 2. Màn hình WordPress tùy chọn

Mã plugin đã có sẵn nhưng **chưa được kích hoạt tự động**. Sau khi có nền WordPress hoạt động, có thể chủ động bật plugin qua Plugins, rồi vào **Tools → Mầm Mart Sandbox**. Không cần WooCommerce để chạy demo này.

Màn hình chỉ cho tài khoản có `manage_options`; thao tác POST phải qua `check_admin_referer`. Kết quả được escape khi hiển thị. Không nhận đường dẫn kho, event hay thông số nhạy cảm từ request. Mỗi lần bấm tạo một bộ dữ liệu giả mới trong thư mục tạm, không nối vào dữ liệu cửa hàng.

T30 luôn được đánh dấu BLOCKED trong runner CLI vì runner không điều khiển phiên WordPress thật. Bằng chứng kiểm tay được theo dõi riêng ở `docs/T30-manual.md`; không suy ra kết quả live từ test giả. Việc bật/tắt plugin và thử giao diện không phải bằng chứng đã tích hợp WooCommerce.

Kiểm quyền/nonce của POST được đặt tại hook `load-tools_page_mammart-marketing`, trước khi WordPress xuất admin header. Test hồi quy bằng API WordPress giả (không thay thế T30):

```powershell
foreach ($scenario in @('denied','missing','invalid','valid','get')) {
    php wp-content/plugins/mammart-marketing/tests/sandbox-guard.php $scenario
}
```

## 3. Các chức năng đã triển khai

- Consent cấp tài khoản, mặc định false nếu không có bản ghi; lưu lịch sử/version/thời điểm. Trường `marketing_opt_in` trong hồ sơ giả là bản chiếu, không được dùng để tự bật consent.
- DOB đã duyệt, không thiếu/không ở tương lai, xét mốc đủ 6 tháng. Không dùng cộng 180 ngày.
- Khóa quyền lợi `(campaign_id, baby_id)`, không chứa DOB version. Kho JSON dùng khóa liên tiến trình `flock`, đọc lại dưới khóa, ghi file tạm rồi thay file trạng thái.
- Coupon giả gắn tài khoản, giảm 15% chỉ khi SKU được đánh dấu true **và** phân loại `weaning-gear`. Thực phẩm luôn bị loại. Không tin giá hoặc cờ đủ điều kiện truyền từ giỏ.
- Kiểm chủ, thời hạn, đã dùng, một mã trên giỏ và một mã trên đơn giả. Mã cũ vẫn kiểm được sau opt-out.
- Gửi giả/retry lấy mã đã lưu, kiểm consent ngay trước mỗi lần gửi. Lỗi mail không xóa quyền lợi hoặc sinh coupon khác.
- `BabyDobApproved` giả cập nhật DOB/version; sự kiện lặp/version cũ/sai chủ bị chặn. Job xét luôn đọc dữ liệu mới, không giữ lịch cấp cũ.
- `SimulatedRedemptionSucceeded` là tín hiệu thử riêng; không giả định Paid hay Completed là dùng thành công.
- Log riêng các hành động `consent`, `issue`, `mail`, `dob`, `validate`, `use`.

## 4. Cấu hình thử và chính sách chưa chốt

`config/sandbox.php` mặc định **không bỏ qua** ngưỡng/trần. Checkout trả `PENDING_POLICY` khi `pricing_policy=UNCONFIRMED`.

| Tùy chọn | Ý nghĩa |
|---|---|
| `SIMULATION_BYPASS_MIN_CAP` | Chỉ thử công thức; không kiểm ngưỡng/trần; thuế giả bằng 0 |
| `SIMULATION_EXPLICIT_MIN_CAP` | Thử với hai số VND được truyền rõ; cơ sở thử là tiền hàng hợp lệ |
| `SIMULATION_HALF_UP_VND` | Quy tắc làm tròn thử, chỉ dùng khi cấu hình rõ |
| `SIMULATION_CALENDAR_NEXT_DAY` | Thử đăng ký muộn: ngày lịch tiếp theo lúc 00:00, theo timezone thử |

Demo cố định dùng `SIMULATION_BYPASS_MIN_CAP` và in lại cấu hình trong output. Không sửa cấu hình mặc định để bật bỏ qua. Mọi kết quả có `label=SIMULATION ONLY`; constructor từ chối mode khác, tỷ lệ khác 15 và mailer không phải FakeMailer.

Quy ước **thử**, không phải chính sách đã duyệt:

- Timezone `Asia/Ho_Chi_Minh`; hết hạn tại đầu tháng kế tiếp, ranh giới exclusive. Ví dụ còn dùng đến 30/11/2026 23:59:59, hết hạn lúc 01/12/2026 00:00:00.
- Đăng ký muộn lấy mốc muộn hơn giữa `registered_at` và lần thay đổi consent đang có hiệu lực. Thời điểm đăng ký, opt-in muộn/bật lại và ngày lịch/24 giờ phải được leader chốt. Nếu có nhánh muộn mà chưa bật policy thử, trả `PENDING_POLICY`.
- DOB thiếu ngày 29/30/31 trong tháng đích: `PENDING_POLICY`, không clamp hoặc chuyển tháng.
- Sửa DOB sau cấp: không cấp thêm, không ghi đè hạn mã cũ; log `existing_issue_policy=PENDING_POLICY` để chờ quyết định về hạn sau sửa DOB.
- SKU/biến thể chưa có cờ rõ: `PENDING_POLICY`; không suy từ nhãn tuổi hay cờ nhắc mua.
- Trạng thái đơn chờ tiền/đã trả/hoàn thành/hủy/hoàn: ghi sự kiện và trả `PENDING_POLICY`, không tự trừ/hoàn lượt.
- Tiền giảm có phần lẻ VND khi chưa có chính sách làm tròn: `PENDING_POLICY`.

PASS của ca kiểm `PENDING_POLICY` nghĩa là module đã dừng đúng chỗ, **không** có nghĩa nhóm đã chốt chính sách đó.

## 5. Dữ liệu và API thử

`fixtures/scenarios.json` có hồ sơ, consent, SKU, giỏ, đơn và kho log trống. Tất cả ID là giả: `B-DEMO-*`, `C-DEMO-*`, `P-DEMO-*`, `O-DEMO-*`; email gửi giả dùng miền `.invalid`.

| Giao diện PHP | Tác dụng |
|---|---|
| `setConsent(customer_id, bool)` | Thay consent trong kho giả, không sửa DOB |
| `issue(baby_id)` | Xét và cấp tối đa một mã trong chiến dịch |
| `send(coupon_id)` | Gửi giả hoặc gửi lại mã cũ |
| `approveDob(event)` | Nhận sự kiện `BabyDobApproved` giả |
| `validate(code, cart)` | Trả trạng thái, tiền và lý do; chưa tiêu lượt |
| `useCoupon(event, cart)` | Nhận kết quả sử dụng giả hoặc ghi trạng thái chờ chính sách |

Đầu vào tối thiểu hồ sơ: `baby_id`, `customer_id`, `birth_date`, `dob_version`, `marketing_opt_in`. Bộ giả bổ sung `dob_approved` và `registered_at` để không đoán DOB đã duyệt hoặc thời điểm đăng ký; consent có `changed_at`, `version`. Đây là phần bổ sung của bộ thử, không cam kết tên cột thật.

Giỏ: `customer_id`, `lines[{sku, quantity}]`, `shipping_vnd`, `coupon_ids[]`. Giá và phân loại đọc từ kho danh mục giả. Phí giao là đầu vào do NL cung cấp; PH không tự chọn mức phí theo địa chỉ.

Sự kiện DOB cần `event_id`, `type=BabyDobApproved`, `baby_id`, `customer_id`, `birth_date`, `dob_version`. Sự kiện dùng giả cần `event_id`, `type=SimulatedRedemptionSucceeded`, `order_id`, `customer_id`, `code`. Xem runner để có ví dụ đầy đủ và đồng hồ của từng ca.

## 6. Phạm vi nghiệm thu và giới hạn

Đầu ra T2-PH: module/code/config, dataset DOB/order giả, ca thử coupon, log cấp/gửi/dùng và danh sách điểm nối thật. Không bắt buộc nhận bản mới T2-YL/NL/KH để thử độc lập. Đối chiếu chi tiết tại `docs/test-matrix.md` và `docs/T3-integration.md`.

Kho JSON chỉ dành cho thử cục bộ trên filesystem hỗ trợ khóa và atomic replace. Chưa kiểm network filesystem, máy nhiều node hoặc tắt máy đột ngột. Không dùng nó làm kho quyền lợi production. Mỗi lần chạy test/demo là một chiến dịch thử riêng trong một kho mới; tính duy nhất được bảo vệ trong cùng kho, không xuyên các kho thử đã reset.

Không có cơ chế tự dọn dữ liệu tạm để tránh xóa nhầm. Thư mục tạm chỉ có dữ liệu giả; có thể dọn thủ công đúng thư mục `mammart-ph-*` / `mammart-marketing-*` sau khi không còn tiến trình thử. Không chạy hai runner cùng lúc nếu muốn giữ riêng các file báo cáo cuối vì chúng dùng cùng `reports/`.

Chưa xác nhận chạy trên nền WordPress/WooCommerce của nhóm, checkout Classic/Block, HPOS, email provider hoặc các module thật. Đầu ra này không tuyên bố toàn luồng thương mại điện tử đã đạt.
