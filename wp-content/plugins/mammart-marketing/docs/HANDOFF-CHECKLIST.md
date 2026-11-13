# T2-PH — rà soát cuối bàn giao

**Kết luận: READY TO HANDOFF cho bản thử độc lập T2-PH.**

Ngày rà soát: 13/11/2026, giờ Việt Nam. Người thực hiện task: Ngụy Trọng Phúc. Branch: `feat/T2-PH-marketing`. Chưa stage, commit, push hoặc chuyển branch.

## 1. Căn cứ và truy vết đầu ra

Đã đọc lại sheet Phân công, dòng 12, task T2-PH:
https://docs.google.com/spreadsheets/d/1SpMd-ozSm8hoZ0Hl9jdOxiSj00tMou3wVNXfDWsXdWg/edit#gid=101&range=A12:N12

Giao ước nghiệp vụ: bản bàn giao 10/11/2026; không lấy code hiện hữu làm chính sách. Dataset có nhãn giả, giữ các trường `baby_id`, `customer_id`, `birth_date`, `dob_version`, `marketing_opt_in`. Thêm trường thời gian/đã duyệt chỉ phục vụ bộ thử, đã mô tả trong README.

| Đầu ra sheet T2-PH | File / bằng chứng | Kết quả |
|---|---|---|
| Module/code/config | `mammart-marketing.php`, `src/`, `config/sandbox.php` | Có |
| Dataset DOB/order giả | `fixtures/scenarios.json`; hồ sơ, consent, SKU, giỏ và đơn giả | Có |
| Ca thử coupon | `tests/run.php`, `tests/issue-worker.php`, `docs/test-matrix.md` | Có, đã chạy T01–T29 |
| Log cấp/gửi/dùng | `reports/demo-log.json`, `reports/test-audit-log.json` | Có, JSON đọc được |
| Điểm nối thật | `docs/T3-integration.md` | Có, chưa kích hoạt adapter thật |
| Phần giả và tham số chưa xác nhận | Nhãn SIMULATION ONLY, config UNCONFIRMED, kết quả PENDING_POLICY | Có |
| Hướng dẫn và kiểm tay | `README.md`, `docs/T30-manual.md` | Có |

## 2. Bằng chứng thực thi

- [x] Chạy `php -l` trên cả 13 file PHP: không lỗi cú pháp.
- [x] Chạy `tests/run.php`: **29 PASS, 0 FAIL, 1 BLOCKED**; exit code 0. BLOCKED là T30 do runner CLI không thực hiện phiên trình duyệt.
- [x] Chạy `tests/sandbox-guard.php` riêng cho denied, missing, invalid, valid, get: **5 PASS bằng stub**, không tính thay T30 live.
- [x] Chạy `bin/demo.php`: tiền hàng 520000, phần hợp lệ 400000, giảm 60000, ship 20000, tổng **480000 VND**.
- [x] T30 **PASS kiểm tay trong phạm vi giao diện/quyền/nonce**, dựa trên ảnh người dùng thực thi; chi tiết và giới hạn trong biên bản T30.
- [x] Báo cáo tự động và kiểm tay được tách riêng; không gọi là 30 PASS tự động.

Lệnh đã chạy từ `D:\T2-PH` bằng PHP `D:\XamPP\php\php.exe` phiên bản 8.0.30. Thời điểm thực thi chính xác nằm trong `reports/test-results.json` (`executed_at_utc`); thời điểm log nghiệp vụ là clock giả.

## 3. Rà quy tắc và bảo mật

| Hạng mục | Cách kiểm / kết luận |
|---|---|
| Consent mặc định false | T01; không có bản ghi thì không opt-in |
| Cấp trùng | T06/T07; khóa JSON liên tiến trình bảo vệ cả kiểm tra và lưu; unique logic campaign+baby, không dùng DOB version |
| DOB sửa đã duyệt | T10–T12; bỏ lịch cũ, chặn version cũ/lặp, không cấp lại sau khi đã cấp |
| Hết hạn | T19; hết hạn exclusive đầu tháng sau theo clock/timezone thử, không +30 ngày |
| Opt-out | T14/T15/T17; ngừng cấp/gửi, mã cũ còn được kiểm đến hạn |
| Gửi lại | T16; cùng code/coupon_id, đọc consent hiện tại trước mỗi lần gửi |
| Phạm vi giảm | T20–T23; catalog giả là nguồn giá/cờ, thực phẩm/hàng khác/ship không được giảm |
| Chủ mã/một mã | T18/T24–T26; kiểm customer và số mã trên giỏ/đơn, chống lặp sự kiện dùng |
| Quyền/nonce | manage_options, hook load trước output, check_admin_referer trước Demo; T30 và 5 test stub |
| Escape kết quả | esc_html khi render; test stub với chuỗi script; không tuyên bố đã pentest live |
| Kết nối thật | Entry chỉ đăng ký admin_menu; không hook checkout, tạo WC_Coupon/WC_Order hoặc gửi mail thật |

Không phát hiện lỗi rõ ràng mới cần sửa trong code PHP ở đợt rà này. Chỉ chỉnh câu mô tả phạm vi trong biên bản T30 để không khẳng định đã đo HTTP 200 của POST hợp lệ từ ảnh.

## 4. Rà thông tin nhạy cảm và phạm vi Git

- [x] Rà source/config/fixture/log/tài liệu; tìm mẫu private key, token GitHub/AWS, gán DB/SMTP password, API key/secret: không phát hiện chuỗi giá trị thật trong phạm vi plugin đã rà.
- [x] Email tìm thấy chỉ dùng miền `.invalid`; ID, DOB, coupon và order trong fixture/log là giả theo bộ thử.
- [x] Không có `wp-config.php`, `.env`, SQL, private key, file ZIP, `.log`, `state.json` hoặc `state.lock` bên trong plugin.
- [x] JSON trong plugin parse hợp lệ; không phát hiện whitespace cuối dòng ở lần quét trước khi thêm checklist.
- [x] Git chỉ báo thư mục plugin mới chưa theo dõi; không có diff ở file tracked của thành viên khác.
- [x] Source `SandboxPage.php` trong repo và website thử đã đối chiếu hash trùng; không thay code site local trong đợt rà này.

Đây là rà bằng mẫu và đọc source, không bảo đảm của một công cụ DLP/pentest toàn hệ thống. Username thử trong biên bản không kèm password. `php_binary` trong báo cáo chỉ là đường dẫn PHP local, không phải thông tin đăng nhập.

Danh sách cần commit (24 file) nằm ở `docs/COMMIT-FILES.txt`; đã gồm README, source, config, fixture, test, tài liệu và báo cáo giả. Không thực hiện git add/commit/push.

Không đưa vào commit: cấu hình XAMPP/Apache/MySQL, database hoặc `wp-config.php` local, dữ liệu thật, cookie/nonce phiên thật, ảnh có mật khẩu, file backup/ZIP tạm, thư mục trạng thái trong temp. `wp-config.php` và SQL chung đã được repo gốc theo dõi nhưng không thuộc thay đổi PH lần này. Không sửa `.gitignore` chung trong đợt rà này.

## 5. Những giới hạn leader cần biết

1. Bản độc lập SIMULATION ONLY; chưa nối hồ sơ YL, dữ liệu KH hoặc checkout/đơn NL thật; chưa kiểm WooCommerce Classic/Block/HPOS.
2. SKU demo không phải whitelist chính thức. CSV tuần 1 trong repo không phải bộ mẹ và bé đã chuẩn hóa; dữ liệu nền thật còn phụ thuộc KH, không giả vờ đã dùng catalog thật.
3. Ngưỡng/trần/cơ sở tính, ngày 29–31, quy tắc đăng ký muộn/bật lại consent, hạn sau sửa DOB, thuế/làm tròn/hàng sale và giữ/trừ/hoàn lượt cần leader xác nhận. Thiếu policy thì PENDING_POLICY; các config trial không là quyết định chính thức.
4. Kho JSON chỉ bảo vệ trong cùng kho thử trên filesystem local. Reset tạo bộ thử mới; chưa có bảo đảm multi-node, network filesystem hoặc mất điện.
5. T30 dùng ảnh thao tác người dùng để đối chiếu. Chưa đo filesystem sau POST bị chặn và ảnh Console chưa mở trường demoRan; không suy rộng kết luận sang các phép đo này.
6. Runner CLI luôn để T30 BLOCKED; kết quả bàn giao phải đọc cùng T30-manual.md. Các ca PENDING_POLICY PASS chỉ chứng minh dừng đúng chỗ.
7. File báo cáo được ghi đè khi chạy lại; leader nên giữ nguyên bộ bằng chứng đã nhận. ZIP tài liệu đã xuất trước đợt rà này không tự cập nhật, bản trong repository là bản hiện tại.

Các giới hạn trên không ngăn bàn giao **bản thử độc lập** theo T2-PH; chúng ngăn việc coi đây là bản tích hợp/production. Deadline theo sheet: 15/11/2026 23:59.
