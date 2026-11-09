# ĐẶC TẢ CHỨC NĂNG, KIẾN TRÚC & ÁNH XẠ DỮ LIỆU WORDPRESS - MẦM MART (BABYNEST)

**Mã nhiệm vụ:** T1-KH  
**Dự án:** Website TMĐT B2C Mẹ & Bé Mầm Mart  
**Tác giả:** Khánh  
**Người nhận bàn giao:** Lê Đình Liêm (Leader)  
**Môi trường:** Laragon Localhost (WordPress 6.6.x + WooCommerce 9.x)  

---

## 1. ĐẶC TẢ CHỨC NĂNG (FUNCTIONAL SPECIFICATIONS)

### 1.1. Phân hệ Quản lý Danh mục & Sản phẩm (Product Catalog Management)
- **Phân cấp danh mục 2 cấp (Category Hierarchy):** Hỗ trợ xây dựng cây danh mục cha - con trực quan (Ví dụ: *Tã - Bỉm > Tã dán*, *Sữa & Thực phẩm > Sữa công thức*).
- **Quản lý thông tin sản phẩm chuẩn:** Lưu trữ đầy đủ Tên sản phẩm, Mã SKU, Giá bán lẻ (Regular Price), Số lượng tồn kho (Stock Quantity), Mô tả ngắn và Ảnh đại diện.
- **Biến thể sản phẩm (Variable Products):** Cho phép cấu hình sản phẩm đa thuộc tính (Kích thước Tã quần M/L/XL, Dung tích bình sữa 160ml/240ml).

### 1.2. Phân hệ Nghiệp vụ nâng cao ngành Mẹ & Bé (Extended Business Logic)
- **Lọc sản phẩm theo độ tuổi của bé (Stage Filtering):** Gắn nhãn giai đoạn phát triển (`0-6m`, `6-12m`, `12-24m`) giúp phụ huynh dễ dàng lọc sản phẩm phù hợp độ tuổi của con.
- **Đánh dấu mua hàng định kỳ (Auto-ship / Subscriptions):** Nhận diện các sản phẩm tiêu hao hàng tháng (Tã/Bỉm, Khăn ướt) nhằm phục vụ tính năng mua định kỳ tự động.
- **Khuyến mãi mốc ăn dặm (Weaning Gear Campaign):** Gắn nhãn bộ dụng cụ ăn dặm (Thìa, bát, yếm) để gợi ý combo khuyến mãi khi bé tròn 6 tháng.

---

## 2. SƠ ĐỒ LUỒNG NGHIỆP VỤ (WORKFLOW DIAGRAMS)

### 2.1. Luồng Import & Đồng bộ Dữ liệu Sản phẩm (Data Import Workflow)
```text
[Nhóm phát triển] ──(Chuẩn bị file CSV 30 sản phẩm)──> [Admin WooCommerce]
                                                               │
                                                       (Tải file CSV lên)
                                                               │
                                                               🗹
                                                [WooCommerce Importer Engine]
                                                               │
                                               (Map cột CSV với CSDL WordPress)
                                                               │
                                                               🗹
                                            [Lưu vào MySQL `mammart_db`]
                                            (Tables: wp_posts, wp_postmeta)
```

### 2.2. Luồng Khách hàng Lọc & Xem Sản phẩm theo Độ tuổi (Stage Filter Workflow)
```text
[Khách hàng] ──> [Trang Danh mục Mầm Mart]
                        │
             (Chọn lọc theo độ tuổi: 6-12m)
                        │
                        🗹
    [Query Database: `meta_key = _product_stage_ids`]
                        │
                        🗹
    [Hiển thị danh sách Sản phẩm & Trạng thái tồn kho]
```

---

## 3. SƠ ĐỒ KIẾN TRÚC HỆ THỐNG (SYSTEM ARCHITECTURE)

```text
+-------------------------------------------------------------------------+
|                         TRÌNH DUYỆT KHÁCH HÀNG / ADMIN                  |
+-------------------------------------------------------------------------+
                                     |  HTTP / HTTPS (Port 80)
                                     v
+-------------------------------------------------------------------------+
|                       MÔI TRƯỜNG LOCAL LARAGON SERVER                    |
|                                                                         |
|  +-------------------------------------------------------------------+  |
|  | WP CORE (WordPress 6.6.x)                                         |  |
|  |  ├── Theme Layer (Storefront / Custom Theme)                    |  |
|  |  └── Plugin Layer (WooCommerce 9.x Core Engine)                  |  |
|  +-------------------------------------------------------------------+  |
|                                    |                                    |
|                                    | MySQL Database Connection          |
|                                    v                                    |
|  +-------------------------------------------------------------------+  |
|  | DATABASE (MySQL / MariaDB: `mammart_db`)                          |  |
|  |  ├── wp_posts & wp_postmeta (Sản phẩm & Thông số Mẹ & Bé)        |  |
|  |  ├── wp_terms & wp_term_taxonomy (Danh mục & Thuộc tính)         |  |
|  |  └── wp_options (Cấu hình hệ thống & WooCommerce Settings)        |  |
|  +-------------------------------------------------------------------+  |
+-------------------------------------------------------------------------+
```

---

## 4. BẢNG ÁNH XẠ DỮ LIỆU WORDPRESS/WOOCOMMERCE (DATA MAPPING TABLE)

Bảng chỉ rõ vị trí lưu trữ thực tế của các thông tin dữ liệu Mầm Mart trong Cơ sở dữ liệu MySQL (`mammart_db`):

| STT | Dữ liệu nghiệp vụ Mầm Mart | Bảng CSDL WordPress (`Table`) | Trường CSDL (`Column / Key`) | Ví dụ dữ liệu thực tế lưu trữ |
| :---: | :--- | :--- | :--- | :--- |
| **1** | Mã sản phẩm (ID) | `wp_posts` | `ID` | `101` |
| **2** | Tên sản phẩm | `wp_posts` | `post_title` | `Tã dán Moony Natural Newborn 62 miếng` |
| **3** | Loại sản phẩm (Simple/Variable) | `wp_term_relationships` | `term_taxonomy_id` | Term slug: `simple`, `variable` |
| **4** | Mã SKU | `wp_postmeta` | `meta_key = '_sku'` | `BM-MOON-NB` |
| **5** | Giá bán lẻ (Regular Price) | `wp_postmeta` | `meta_key = '_regular_price'` | `385000` |
| **6** | Số lượng tồn kho (Stock) | `wp_postmeta` | `meta_key = '_stock'` | `100` |
| **7** | Trạng thái tồn kho | `wp_postmeta` | `meta_key = '_stock_status'` | `instock` |
| **8** | Danh mục sản phẩm (Categories) | `wp_terms` & `wp_term_taxonomy` | `name` & `taxonomy='product_cat'` | `Tã dán`, `Sữa công thức` |
| **9** | Thuộc tính sản phẩm (Attributes) | `wp_postmeta` | `meta_key = '_product_attributes'` | `Kích thước`, `Dung tích` |
| **10** | Lọc theo Độ tuổi của bé | `wp_postmeta` | `meta_key = '_product_stage_ids'` | `0-6m`, `6-12m` |
| **11** | Đánh dấu Mua định kỳ | `wp_postmeta` | `meta_key = '_is_recurring_eligible'` | `1` (True) |
| **12** | Đánh dấu Bộ đồ dùng ăn dặm | `wp_postmeta` | `meta_key = '_is_weaning_gear'` | `1` (True) |
| **13** | Đường dẫn hình ảnh sản phẩm | `wp_posts` (Attachment) | `guid` | `https://via.placeholder.com/600` |