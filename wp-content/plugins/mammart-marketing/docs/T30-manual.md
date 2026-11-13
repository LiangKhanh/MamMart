# T30 — kiểm tay WordPress local

Ngày theo dõi: 12/11/2026, giờ Việt Nam. SIMULATION ONLY.

Môi trường người dùng: `http://localhost:8080/mammart-t2ph/`, bản plugin copy riêng ngoài repository. Không tự đồng bộ source giữa hai thư mục.

## Bằng chứng đã quan sát từ ảnh người dùng

| Nhánh | Quan sát | Kết luận |
|---|---|---|
| Admin POST hợp lệ, trước sửa | Admin chạy được demo có nhãn SIMULATION ONLY | Đạt nhánh thao tác admin của bản cũ |
| Subscriber mở URL trực tiếp | WordPress báo không được truy cập | Đạt nhánh quyền người dùng của bản cũ |
| Thiếu/sai nonce, trước sửa | HTTP 200, không redirect, `nonceRejected=true` | Lỗi mã HTTP; chưa đạt yêu cầu 403 |

Không khẳng định `demoRan=false` từ ảnh vì trường này chưa được mở rộng để quan sát.

## Bản sửa và kiểm thử hồi quy

Chuyển kiểm quyền, nonce và xử lý POST sang hook load của trang, trước khi WordPress xuất admin header. Render chỉ hiển thị kết quả đã chuẩn bị và tiếp tục kiểm quyền. Không đổi WordPress core, Apache hoặc MySQL.

Đã chạy 5 kịch bản API giả bằng `tests/sandbox-guard.php`: denied, missing, invalid, valid, get. Cả 5 đạt, gồm kiểm không output trước guard, không chạy demo khi bị chặn, và escape nội dung. Đây là kiểm hồi quy bằng stub; không phải kết quả T30 live.

## Phạm vi kiểm tay và phép đo bổ sung

- Admin GET và POST hợp lệ: thấy trang và kết quả demo; ảnh không đo trực tiếp mã HTTP của POST hợp lệ.
- Subscriber truy cập URL trực tiếp: bị chặn.
- Chưa đăng nhập: chuyển tới đăng nhập, không chạy demo.
- POST thiếu nonce: HTTP 403, có thông báo nonce, không chạy demo.
- POST nonce sai: HTTP 403, có thông báo nonce, không chạy demo.
- Kiểm không sinh kho demo mới cho POST bị từ chối nếu cần chứng minh tác động filesystem.

## Kết quả kiểm tay đã bổ sung ngày 13/11/2026

Người dùng thực hiện thao tác trên trình duyệt; kết quả dưới đây được đối chiếu từ ảnh trong hội thoại, không phải công cụ CLI tự chạy phiên trình duyệt.

| Nhánh | Bằng chứng | Kết quả |
|---|---|---|
| Admin chạy POST hợp lệ sau sửa | Tài khoản `phuc_test_admin`, demo trả `SIMULATED_USED`, discount=60000, shipping=20000, total=480000 | PASS |
| Subscriber mở URL trực tiếp sau sửa | Người dùng đăng nhập `phuc_test_user` theo hướng dẫn, mở URL sandbox trong cửa sổ ẩn danh; ảnh cuối ngày 13/11 cho thấy `Sorry, you are not allowed to access this page.` | PASS |
| Thiếu nonce sau sửa | Console: status=403, redirected=false, nonceRejected=true | PASS cho phản hồi HTTP và từ chối nonce |
| Nonce sai sau sửa | Console: status=403, redirected=false, nonceRejected=true | PASS cho phản hồi HTTP và từ chối nonce |
| Chưa đăng nhập sau sửa | Cửa sổ ẩn danh mở URL sandbox được đưa về `wp-login.php?redirect_to=...tools.php...` | PASS |

Đã kiểm hash `src/SandboxPage.php` trong repo và bản website local trùng nhau:
`F76621B1C30D36FCC80DD52282D69348E80B70DC4B75424FB3B84D12881271C6`.

Giới hạn: ảnh Console chưa mở rộng trường `demoRan`; chưa đo thay đổi filesystem khi request bị từ chối. Không khẳng định đã chạy các phép đo này. Test stub và source kiểm guard trước Demo, nhưng không thay thế bằng chứng live về filesystem.

Kết luận T30: **PASS kiểm tay cho các nhánh giao diện/quyền/nonce nêu trên trên WordPress local**, căn cứ ảnh kết quả người dùng cung cấp. Kiểm tra thay đổi filesystem và trường `demoRan` không nằm trong kết luận PASS này vì chưa quan sát trực tiếp. Không suy rộng thành kiểm thử checkout WooCommerce, email hay thanh toán thật.

Tổng hợp bàn giao: **T01–T29 PASS tự động; T30 PASS kiểm tay**. Báo cáo CLI vẫn giữ 29 PASS, 0 FAIL, 1 BLOCKED vì runner không tự chạy phiên WordPress; đọc cùng biên bản này, không chuyển thành 30 PASS tự động.
