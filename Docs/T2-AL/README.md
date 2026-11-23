# T2-AL — Giao diện và giỏ hàng (Mầm Mart)

Theme con `wp-content/themes/mammart-ui` (cha: Twenty Twenty-Five).

## Dựng lại (máy đã có WP + WooCommerce chạy)
1. `git checkout t2-al/giao-dien-gio`
2. Từ thư mục gốc repo: `php Docs/T2-AL/setup_t2al.php` — bật theme, VND, trang Cửa hàng/Giỏ hàng/Thanh toán/Tài khoản (slug tiếng Việt), bắt buộc đăng nhập khi thanh toán, permalink `/%postname%/`.
3. Dữ liệu thử cho ca giỏ/biến thể/tồn: `php Docs/T2-AL/seed_test_products.php` (13 sản phẩm giả, có size hết hàng, tồn thấp, sản phẩm ẩn). Không thay CSV của T2-KH.
4. Laragon: bật `mod_rewrite` (Apache) để dùng permalink đẹp; vào Cài đặt → Đường dẫn tĩnh bấm Lưu nếu 404.

## Không đưa vào repo
`wp-config.php` thật, mật khẩu, khóa. Tài khoản thử `khach_a`, `khach_b` chỉ tạo cục bộ khi chạy kiểm thử.
