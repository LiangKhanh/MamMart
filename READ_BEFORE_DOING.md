# 🛒 MẦM MART (BABYNEST) - B2C E-COMMERCE PLATFORM

> **Đồ án môn học:** Xây dựng Website Thương mại điện tử B2C Mẹ & Bé
> **Nhiệm vụ Tuần 1 (Task T1-KH):** Khởi tạo bản nền website, thiết lập CSDL, dữ liệu sản phẩm mẫu & Đặc tả tài liệu kỹ thuật.
> **Người thực hiện:** Khánh
> **Nhận bàn giao:** Lê Đình Liêm (Leader) & Các thành viên trong nhóm.

## 📌 1. TỔNG QUAN VỀ DỰ ÁN & CẤU TRÚC THƯ MỤC

Dự án **Mầm Mart** được dựng trên nền tảng **WordPress 6.6.x** kết hợp plugin **WooCommerce 9.x**, vận hành trên môi trường máy chủ cục bộ **Laragon** (Windows).

### 🗂️ Cấu trúc các thư mục quan trọng trong Repository:

```
mammart/
├── README.md                           # File hướng dẫn chạy project & cấu trúc hệ thống (File này)
├── .gitignore                          # Cấu hình bỏ qua các file rác/cache của WordPress
├── Data/
│   └── mammart_sample_30_products.csv  # File CSV chứa 30 sản phẩm Mẹ & Bé mẫu chuẩn WooCommerce
├── Docs/
│   └── T1-KH_Design_Specification.md   # Tài liệu đặc tả chức năng, sơ đồ luồng, kiến trúc & ánh xạ CSDL
├── SQL/
│   └── mammart_backup_t1.sql           # File sao lưu cơ sở dữ liệu MySQL khởi tạo ban đầu
├── wp-admin/                           # Thư mục quản trị hệ thống WordPress
├── wp-content/                         # Thư mục chứa Themes, Plugins & Uploads media
├── wp-includes/                        # Thư mục thư viện lõi của WordPress
└── wp-config.php                       # File cấu hình kết nối CSDL (Xem hướng dẫn thiết lập bên dưới)

```

## 🛠️ PHẦN 1: CHUẨN BỊ MÔI TRƯỜNG (CHO MÁY MỚI HOÀN TOÀN)

### Bước 1: Tải và cài đặt Laragon

1. Truy cập trang chủ Laragon: <https://laragon.org/download/>

2. Tải bản **Laragon Full** (bao gồm Apache 2.4, Nginx, MySQL 8.x, PHP 8.x).

3. Cài đặt Laragon vào đường dẫn mặc định: `C:\laragon\` (hoặc đĩa làm việc của bạn).

4. Mở Laragon, bấm **Start All** để khởi động Web Server (Apache/Nginx) và Database (MySQL).

## 📥 PHẦN 2: PULL REPOSITORY VỀ MÁY LOCAL

### Bước 2: Clone/Pull mã nguồn từ GitHub

1. Mở **Git Bash** hoặc Terminal tại thư mục `www` của Laragon (`C:\laragon\www\` hoặc `E:\Download\laragon\www\`):

   ```
   cd C:\laragon\www
   
   ```

2. Clone Repository dự án về máy:

   ```
   git clone https://github.com/LiangKhanh/MamMart.git mammart
   
   ```

3. Mở thư mục `mammart` bằng Visual Studio Code:

   ```
   cd mammart
   code .
   
   ```

## 🗄️ PHẦN 3: KẾT NỐI CƠ SỞ DỮ LIỆU & CẤU HÌNH WEBSITE

### Bước 3: Tạo CSDL & Import File Sao Lưu SQL

1. Mở phần mềm **HeidiSQL** (hoặc phpMyAdmin) sẵn có trong Laragon (Bấm nút **Database** trên giao diện Laragon).

2. Thông số kết nối mặc định của Laragon:

   * **Hostname / IP:** `localhost`

   * **User:** `root`

   * **Password:** *(Để trống hoàn toàn)*

   * **Port:** `3306`

3. Bấm chuột phải vào `Laragon.MySQL` $\rightarrow$ Chọn **Create new** $\rightarrow$ **Database** $\rightarrow$ Đặt tên chính xác là: **`mammart_db`** (Collation: `utf8mb4_unicode_ci`).

4. Nhấp chọn database `mammart_db` vừa tạo:

   * Chọn menu **File** $\rightarrow$ **Run SQL file...** (hoặc phím tắt `Ctrl + Alt + F`).

   * Trỏ đến file sao lưu có sẵn trong Repo: `SQL/mammart_backup_t1.sql`.

   * Bấm **Open** để import toàn bộ cấu trúc bảng và 30 sản phẩm mẫu vào database.

### Bước 4: Kiểm tra File cấu hình WordPress (`wp-config.php`)

Mở file `wp-config.php` tại thư mục gốc `mammart` và đảm bảo các thông số kết nối CSDL chuẩn như sau:

```
define( 'DB_NAME', 'mammart_db' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );

```

## 🖥️ PHẦN 4: KHỞI CHẠY & ĐĂNG NHẬP HỆ THỐNG

### Bước 5: Truy cập Website & Trang Quản trị

* **Trang chủ cửa hàng:** Truy cập đường dẫn `http://localhost/mammart` (hoặc `http://mammart.test`).

* **Trang Quản trị Admin:** Truy cập `http://localhost/mammart/wp-admin`

* **Thông tin đăng nhập Admin mặc định:**

  * **Username:** `admin`

  * **Password:** `admin123`

## 📖 PHẦN 5: CÁCH SỬ DỤNG VÀ CHỨC NĂNG CỦA TỪNG FILE/THƯ MỤC

1. **`README.md` (File này):** Hướng dẫn nhanh cho thành viên mới setup máy và nắm rõ quy trình nộp code.

2. **`Data/mammart_sample_30_products.csv`:**

   * Dùng để import lại dữ liệu sản phẩm chuẩn WooCommerce nếu CSDL bị lỗi.

   * Cách import: Vào Admin $\rightarrow$ **Products** $\rightarrow$ **Import** $\rightarrow$ Chọn file này.

3. **`Docs/T1-KH_Design_Specification.md`:**

   * Tài liệu đặc tả kỹ thuật chi tiết: chứa sơ đồ luồng nghiệp vụ, sơ đồ kiến trúc hệ thống và **bảng ánh xạ CSDL** (chỉ rõ trường dữ liệu nào nằm ở bảng `wp_posts`, `wp_postmeta`, `wp_terms`...).

   * **Đọc kỹ file này trước khi code tính năng mới** để biết đúng vị trí lưu trữ dữ liệu.

4. **`SQL/mammart_backup_t1.sql`:** File sao lưu database chuẩn. Sau khi hoàn thành một chức năng lớn, thành viên cần Export SQL mới và lưu đè vào file này.

## 🔄 PHẦN 6: QUY TRÌNH LÀM VIỆC VỚI GIT & BÀN GIAO CODE

Để tránh xung đột code (conflict) giữa các thành viên:

1. **Trước khi bắt đầu làm bài:** Luôn kéo code mới nhất về:

   ```
   git pull origin main
   
   ```

2. **Sau khi hoàn thành chức năng mới:**

   * Xuất file backup CSDL mới vào `SQL/mammart_backup_t1.sql` (nếu có thay đổi DB/Plugin).

   * Commit và push bài lên GitHub:

     ```
     git add .
     git commit -m "feat(Tên_Thành_Viên): Mô tả ngắn gọn chức năng vừa hoàn thành"
     git push origin main
     
     ```