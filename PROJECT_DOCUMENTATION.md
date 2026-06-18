# TÀI LIỆU CHI TIẾT HỆ THỐNG RAMBOWOON MANAGER (v6.3 Platinum)

Chào mừng bạn đến với tài liệu hướng dẫn kỹ thuật toàn diện của hệ thống **RamboWoon Manager**. Đây là hệ thống tự động hóa chuyên biệt dùng để đóng gói, triển khai (Deploy), cấu hình bảo mật, đồng bộ dữ liệu đám mây (Cloud Transfer) và tối ưu hóa tài nguyên hình ảnh/font chữ cho dòng mã nguồn CMS Laravel (Nasanic Core).

---

## 1. KIẾN TRÚC TỔNG QUAN HỆ THỐNG
Hệ thống hoạt động theo mô hình **Manager - Client/Bridge**:
1. **RamboWoon Manager (Local App)**: Chạy trên môi trường local (máy cá nhân/server local). Nó đảm nhận vai trò là giao diện điều khiển (Dashboard UI), quét thư mục dự án local, xử lý font chữ, tối ưu hóa WebP, trích xuất cấu hình local và điều phối các tác vụ deploy.
2. **RamboWoon Bridge (`bridge.php` - v6.3 Platinum)**: Một tập lệnh độc lập cực kỳ mạnh mẽ được tải lên các Hosting (Demo & Production). Nó đóng vai trò là "bộ não" thực thi từ xa: giải nén mã nguồn, import SQL, tạo database, tạo email, kích hoạt bảo mật, tự động dọn dẹp và tự hủy (Self-Destruct) để bảo vệ hệ thống tuyệt đối.

```
[ Local Projects ] ──► [ Manager API (api.php) ] ──► [ DirectAdmin / FTP / Cloudflare API ]
                                │
                                ├──► Đóng gói & Tải lên (FTP/DA API)
                                │
                                ▼
                       [ Remote Bridge (bridge.php) ]
                                │
          ┌─────────────────────┴─────────────────────┐
          ▼                                           ▼
   [ Server Demo ] ◄───────── Cloud Transfer ────────► [ Server Production ]
 (Tự đổi tên _old)                               (Tự khóa bằng lock.php)
```

---

## 2. BẢN ĐỒ THƯ MỤC & CHI TIẾT TỪNG COMPONENT

### Thư mục `core/` (Các Engine Cốt Lõi)

#### 1. [`ProjectScanner.php`](file:///f:/RBWStack/www/RBWManager/core/ProjectScanner.php)
- **Chức năng**: Quét thư mục gốc để tìm các thư mục dự án.
- **Chi tiết**:
  - Quét cấu trúc phân cấp dạng tháng năm `YYYY_MM` (ví dụ: `2026_05/`).
  - Lọc và loại bỏ các thư mục hệ thống như `.git`, `node_modules`, `vendor`, `logs`, `backups`.
  - Phân loại danh mục dự án dựa theo thời gian tạo để hiển thị lên UI bản mới nhất trước.

#### 2. [`AutoMediaPipeline.php`](file:///f:/RBWStack/www/RBWManager/core/AutoMediaPipeline.php)
- **Chức năng**: Pipeline phân tích ảnh và kết nối DB dự án.
- **Chi tiết**:
  - Tự động parse file `.env` của Laravel để lấy thông tin Database local và thiết lập kết nối PDO.
  - Phân tích config type (`config/type-*.php`) để đọc các tỷ lệ kích thước hình ảnh mong muốn.
  - Tạo biểu thức Regex tự động từ các key cấu hình để phân loại file ảnh.
  - Kiểm tra xem tỷ lệ ảnh thực tế có khớp tỷ lệ quy định (`isRatioMatch`) với sai số cho phép không.

#### 3. [`ConfigManager.php`](file:///f:/RBWStack/www/RBWManager/core/ConfigManager.php)
- **Chức năng**: Quản lý tệp lưu trữ trạng thái các dự án [`projects.json`](file:///f:/RBWStack/www/RBWManager/projects.json).
- **Chi tiết**:
  - Lưu cấu hình triển khai riêng của từng dự án (Thông tin FTP, DB Prod, Domain).
  - Ghi lịch sử hoạt động (Deploy Demo, Publish Production, Sync Tools) tối đa 50 log gần nhất.
  - Lưu trạng thái khóa/mở khóa thao tác (`lock_demo`, `lock_production`).

#### 4. [`DeploymentService.php`](file:///f:/RBWStack/www/RBWManager/core/DeploymentService.php)
- **Chức năng**: Điều phối các tác vụ Deploy ở mức độ dịch vụ.
- **Chi tiết**:
  - **`generateDemoDbName`**: Tạo hậu tố tên Database tự động từ tháng và tên dự án, khống chế độ dài tối đa 13 ký tự để tránh vượt giới hạn 24 ký tự của DirectAdmin (11 ký tự username + 13 ký tự suffix).
  - **`pack`**: Sử dụng công cụ nén `tar` của hệ thống để nén nhanh mã nguồn (loại trừ các thư mục rác).
  - **`upload`**: Hỗ trợ upload đa cơ chế qua FTP hoặc fallback qua DirectAdmin API File Manager. Ngăn chặn ghi đè nếu phát hiện thư mục chứa hậu tố `_old` (dấu hiệu dự án đã bàn giao).
  - Tích hợp các hàm gọi DirectAdmin API để tạo DB/User/Password, tạo Email `noreply`, kích hoạt SSL Let's Encrypt và đổi phiên bản PHP.
  - Hỗ trợ gọi API Cloudflare để tạo Widget Turnstile bảo mật tự động.

#### 5. [`PackagingService.php`](file:///f:/RBWStack/www/RBWManager/core/PackagingService.php)
- **Chức năng**: Đóng gói và tải mã nguồn từ Demo về Local.
- **Chi tiết**:
  - Đồng bộ `bridge.php` mới nhất lên Demo.
  - Gọi action `package` trên Bridge từ xa để nén toàn bộ mã nguồn + kết xuất database mẫu.
  - Tải gói nén ZIP về thư mục `download/` ở local an toàn thông qua streaming kết nối.

#### 6. [`SchemaManager.php`](file:///f:/RBWStack/www/RBWManager/core/SchemaManager.php)
- **Chức năng**: Trình quản lý cấu hình các file config type của dự án.
- **Chi tiết**:
  - Đọc và ghi các file cấu hình php dạng mảng ngắn `[]` (`var_export` kết hợp thay thế regex).
  - Tự động chuẩn hóa lề thụt dòng (4 spaces).
  - Có các logic đặc thù cho dự án News/Static: tự động loại bỏ cấu hình `brand`, kích hoạt mặc định các thuộc tính cho `tin-tuc`.
  - Tự động tiêm (inject) logic hình thức thanh toán động phụ thuộc vào biến `$configSetting['order']`.

#### 7. [`RemoteClient.php`](file:///f:/RBWStack/www/RBWManager/core/RemoteClient.php)
- **Chức năng**: Client HTTP/FTP chuyên dụng tương tác trực tiếp với API bên ngoài.
- **Chi tiết**:
  - Chứa các hàm cURL hỗ trợ POST/GET có giữ session, theo dõi chuyển hướng (redirect), timeout dài 10 phút cho các tệp tin lớn.
  - FTP Client: Hỗ trợ tự động tạo thư mục bị thiếu (`CURLOPT_FTP_CREATE_MISSING_DIRS`).
  - DirectAdmin Client: Thực thi các lệnh qua API File Manager (mkdir, chmod 755, upload file, delete file), API SSL Let's Encrypt, Selector PHP.

#### 8. [`ProjectDeployer.php`](file:///f:/RBWStack/www/RBWManager/core/ProjectDeployer.php)
- **Chức năng**: Đảm nhận tốc độ thực thi tại local dựa trên công nghệ gốc của OS Windows.
- **Chi tiết**:
  - Sao chép thư mục cực nhanh bằng lệnh hệ thống `xcopy /E /I /H /Y`.
  - Giải nén tệp tin bằng công cụ hệ thống `tar -xf`.
  - Khởi tạo cơ sở dữ liệu MySQL local qua mysqli.
  - **Import SQL tối ưu**: Đọc tệp SQL local, lọc bỏ các lệnh hệ thống dễ gây lỗi (`SET`, `START TRANSACTION`, `COMMIT`, `CREATE DATABASE`, `USE`), thực thi lệnh gộp đa tầng (`multi_query`) kết hợp tắt kiểm tra khóa ngoại (`SET FOREIGN_KEY_CHECKS=0`) cho tốc độ vượt trội.

#### 9. [`ImageTrimService.php`](file:///f:/RBWStack/www/RBWManager/core/ImageTrimService.php)
- **Chức năng**: Cắt bỏ viền trắng/viền trong suốt thừa thãi của hình ảnh.
- **Chi tiết**:
  - Hỗ trợ các định dạng PNG, JPG, GIF, WEBP thông qua thư viện GD.
  - Quét ma trận điểm ảnh từ bốn góc để xác định tọa độ biên chứa pixel thực dựa trên độ lệch màu (Tolerance).
  - Cắt và lưu tệp tin tạm thời, sau đó ghi đè tệp tin gốc để tối ưu dung lượng ảnh mà không làm hỏng tính trong suốt của nền.

---

### Các File Điều Hướng & Tiện Ích Ở Thư Mục Gốc

#### 10. [`api.php`](file:///f:/RBWStack/www/RBWManager/api.php)
- **Chức năng**: Bộ định tuyến API chính (Router) tiếp nhận yêu cầu từ giao diện frontend `index.php`.
- **Đặc điểm nổi bật**:
  - **Chế độ Chạy nền (Background Jobs)**: Khi deploy hoặc tải mã nguồn (tác vụ tốn nhiều thời gian), `api.php` sẽ ghi payload ra tệp JSON tạm và dùng lệnh dòng lệnh `start "" /B php.exe api.php --action=...` (trên Windows) hoặc `exec("php api.php ... &")` (trên Linux) để chạy ngầm, phản hồi ngay trạng thái `queued` về cho client để tránh bị quá hạn kết nối (timeout).
  - Quản lý nhật ký tiến trình (Log Jobs) ghi vào thư mục `logs/` theo thời gian thực để UI polling cập nhật tiến độ cho người dùng.
  - Đầy đủ các case xử lý: Deploy, Publish, Đồng bộ, Chuyển đổi WebP/Trimming hình ảnh, Cài đặt Font chữ, Đổi phiên bản PHP, Cài đặt Let's Encrypt.

#### 11. [`bridge.php`](file:///f:/RBWStack/www/RBWManager/bridge.php) (v6.3 Platinum)
- **Chức năng**: Tệp tin cầu nối triển khai chạy trực tiếp trên máy chủ Demo & Production.
- **Tính năng độc quyền**:
  - **Tự động nhận diện cấu hình DB**: Đọc và giải mã trực tiếp file `.env` Laravel để tự cấu hình Database local mà không cần phụ thuộc vào dữ liệu Manager truyền qua.
  - **Đóng gói chuyên sâu (Clean Packaging)**: Khi được gọi, nó xuất Database mẫu (giữ lại các bảng hệ thống bắt buộc như `setting`, `user`, `roles`, `city`, `district` và làm sạch các bảng dữ liệu rác), nén mã nguồn loại bỏ thư mục rác, đặc biệt là **tự động cắt bỏ các route cấu hình nhạy cảm** (như BackupController, ClearDataController) trong file `web.php` để đảm bảo an ninh tuyệt đối trước khi bàn giao.
  - **Cơ chế Tự hủy (Self-Destruct)**: Khi gọi `action=cleanup`, nó sẽ xóa sạch các file cài đặt `dist.zip`, `dist.sql` và tự xóa chính nó (`bridge.php`) khỏi host để không để lại dấu vết.
  - **Cơ chế Khóa tự động (Auto-Lock UI)**: Đổi tên thư mục Demo thành `[folder]_old` ngay sau khi tải mã nguồn bàn giao thành công để khóa quyền sử dụng của khách hàng.
  - **Cơ chế lock.php**: Tự động chèn tệp tin khóa `lock.php` vào `.htaccess` (DirectoryIndex) để khóa trang web Production khi chưa bàn giao.

#### 12. [`seed_images.php`](file:///f:/RBWStack/www/RBWManager/seed_images.php) & [`scan_images.php`](file:///f:/RBWStack/www/RBWManager/scan_images.php)
- **Chức năng**: Quản lý kho ảnh mẫu và tự động tạo dữ liệu mẫu cho dự án.
- **Chi tiết**:
  - `scan_images.php`: Quét thư mục ảnh dự án, dùng Regex và so khớp kích thước/tỷ lệ để phân loại ảnh vào đúng các subtype thích hợp.
  - `seed_images.php`: Đọc cấu hình categories (list, cat, item, sub) trong dự án, tự động tạo cấu trúc danh mục đa cấp, copy và phân bổ ảnh mẫu, tự động tạo văn bản tiếng Việt ngẫu nhiên, sinh slug tương thích hoàn toàn với bảng `table_slug`.

#### 13. [`auto_update.php`](file:///f:/RBWStack/www/RBWManager/auto_update.php)
- **Chức năng**: Pipeline tự động xoay vòng ảnh mẫu và dọn dẹp rác.
- **Chi tiết**:
  - Tự động thay thế ảnh cũ trong Database bằng cách copy ảnh mẫu mới từ assets, đổi tên ngẫu nhiên kết hợp timestamp, cập nhật đường dẫn vào Database và thực hiện **unlink xóa bỏ ảnh cũ thực tế trên đĩa cứng** để giải phóng dung lượng đĩa của host.

#### 14. [`converter.php`](file:///f:/RBWStack/www/RBWManager/converter.php) & [`ai_checker.php`](file:///f:/RBWStack/www/RBWManager/ai_checker.php)
- **Chức năng**: Tiện ích phụ trợ độc lập.
- **Chi tiết**:
  - `converter.php`: Tiếp nhận ảnh upload trực tiếp từ trình duyệt, chuyển đổi hàng loạt sang định dạng WebP/JPG chất lượng cao, đóng gói ZIP phản hồi tải về, tự động quét dọn tệp tạm cũ sau 1 tiếng.
  - `ai_checker.php`: Kiểm tra tình trạng kết nối API Key và Quota/Rate Limit của các dòng model Gemini và Claude.

---

## 3. QUY TRÌNH HOẠT ĐỘNG KHÉP KÍN (WORKFLOWS)

### 3.1 Quy trình Triển khai Demo (Deploy Demo)
```
[Manager] Nén mã nguồn local thành dist.zip & Trích xuất SQL local thành dist.sql
     │
     ▼
[Manager] Gọi DirectAdmin API trên server Demo để khởi tạo DB & User ngẫu nhiên
     │
     ▼
[Manager] Tải bridge.php, dist.zip, dist.sql lên thư mục đích thông qua FTP/DA API
     │
     ▼
[Manager] Gọi kích hoạt bridge.php?action=deploy
     │
     ├─► [Bridge] Giải nén dist.zip bằng hệ thống unzip (hoặc ZipArchive)
     ├─► [Bridge] Khôi phục cấu hình .env cũ & chèn thông tin DB mới
     ├─► [Bridge] Import dist.sql thông qua CLI mysql (hoặc PDO)
     ├─► [Bridge] Xóa tệp tạm dist.zip và dist.sql
     │
     ▼
[Manager] Cập nhật lịch sử và lưu thông tin DB vào projects.json để quản lý
```

### 3.2 Quy trình Triển khai Production cực kỳ nghiêm ngặt (Publish Production)
Quy trình này áp dụng các tiêu chuẩn an toàn cao để bảo vệ tính toàn vẹn dữ liệu:
1. **Kiểm tra SQL Integrity**: Bridge sẽ kiểm tra dung lượng tệp tin SQL trước khi import. Nếu dưới 100 bytes, hệ thống sẽ từ chối deploy để tránh ghi đè dữ liệu rỗng lên DB Production.
2. **Khởi tạo Turnstile & Bảo mật**: Tự động sinh `RANDOMKEY` bảo mật theo công thức `md5(salt1 + db_name + salt2)`. Gọi API Cloudflare Turnstile để đăng ký sitekey bảo mật Form.
3. **Trì hoãn kích hoạt (Propagation Delay)**: Hệ thống tạm nghỉ `sleep(2)` trước khi import để đảm bảo DirectAdmin đã kích hoạt xong toàn bộ quyền hạn cho User DB mới tạo trên hệ thống máy chủ.
4. **Đồng bộ đám mây trực tiếp (Cloud Transfer)**: 
   - Manager gửi yêu cầu lên Bridge Demo.
   - Bridge Demo tự đóng gói mã nguồn + DB thành gói ZIP/SQL sạch tại Demo.
   - Bridge Demo gọi Bridge Production qua API từ xa, truyền link tải nội bộ.
   - Bridge Production tải trực tiếp gói dữ liệu từ Demo sang (truyền tải trực tiếp giữa 2 server với tốc độ cực nhanh, không đi qua máy local).
   - Bridge Production tự động thực hiện quy trình giải nén, import DB, thiết lập `.env` và tự động đổi toàn bộ link tên miền Demo thành tên miền Production trong Database.
5. **Kích hoạt Force-Lock**: Bridge Production tự động chèn `lock.php` làm DirectoryIndex trong `.htaccess` để khóa hiển thị giao diện trang web khách hàng, đảm bảo dữ liệu luôn được an toàn trước khi bàn giao chính thức.

### 3.3 Quy trình Quản lý & Cài đặt Font chữ thông minh
Hệ thống cho phép tích hợp font chữ tối ưu hóa cho SEO & Performance:
- **Font Local**: Manager quét thư mục thư viện font local. Khi người dùng chọn các biến thể (variants) của font, Manager sẽ copy các file WOFF/WOFF2 tương ứng vào dự án local và tự động ghi đè/nối nội dung cấu hình `@font-face` vào file `assets/css/fonts.css`, tự động chèn thuộc tính tối ưu hiển thị `font-display: swap;`.
- **Google Fonts**: Tiếp nhận link import font từ Google Fonts, tự động kiểm tra xem font đã tồn tại trong CSS chưa để tránh trùng lặp, sau đó ghi trực tiếp cú pháp `@import` lên đầu file `fonts.css`.

---

## 4. CƠ CHẾ BẢO MẬT ĐA LỚP ĐỘC QUYỀN
Hệ thống RamboWoon Manager được thiết kế với tư duy bảo mật cao:
- **Tự động đổi tên Demo (`_old Lock`)**: Sau khi tải mã nguồn bàn giao thành công, Bridge Demo tự động đổi tên thư mục chạy demo thành `[folder]_old`. Điều này ngăn chặn việc khách hàng tiếp tục sử dụng website thử nghiệm sau khi đã bàn giao mã nguồn.
- **Tự hủy Bridge (Self-Destruct)**: `bridge.php` sẽ tự động xóa chính nó trên đĩa cứng của host ngay khi nhận lệnh `cleanup` hoặc sau khi tác vụ cài đặt kết thúc, triệt tiêu hoàn toàn nguy cơ hacker dò quét và khai thác backdoor qua tệp cầu nối.
- **Lọc mã nguồn bàn giao**: Quá trình đóng gói loại bỏ hoàn toàn các controller quản lý sao lưu hoặc xóa dữ liệu nhạy cảm (`BackupController`, `ClearDataController`) khỏi tệp tin định tuyến `web.php` trước khi nén để tránh rò rỉ mã nguồn quản trị.

---
*Tài liệu này được biên soạn chi tiết bởi Antigravity nhằm giúp lập trình viên nắm bắt toàn bộ dòng chảy dữ liệu của RamboWoon Manager chỉ trong một lần đọc.*
