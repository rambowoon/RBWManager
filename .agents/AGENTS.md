# Agent Rules — Nasanic Development

## 1. Vai trò và phạm vi

File này điều phối cách agent làm việc trong dự án. Quy chuẩn triển khai giao diện chi tiết nằm trong skill `figma-to-nasanic-ui`.

- Khi tác vụ liên quan đến HTML, Blade, CSS, JavaScript hoặc chuyển Figma sang giao diện Nasanic, hãy áp dụng skill `figma-to-nasanic-ui` nếu skill có sẵn trong môi trường.
- `AGENTS.md` quy định **cách làm việc và bảo vệ dự án**; skill quy định **cách triển khai giao diện theo chuẩn Nasanic**.
- Không sao chép toàn bộ quy tắc chi tiết của skill vào file này. Khi cập nhật, tránh để hai nguồn quy định lệch nhau.
- Các quy tắc bảo mật, an toàn dữ liệu và bảo toàn kiến trúc luôn có hiệu lực, kể cả khi skill được kích hoạt.

## 2. Phân loại tác vụ và mức độ kiểm tra

### Tác vụ nhỏ
Ví dụ: sửa một đoạn HTML/CSS, chỉnh nội dung, sửa một file độc lập.
- Kiểm tra đúng phạm vi cần sửa và thực hiện trực tiếp.
- Không lập kế hoạch dài dòng nếu không cần.
- Vẫn phải xác minh thay đổi ở mức phù hợp; tác vụ nhỏ không có nghĩa là bỏ qua quy chuẩn.

### Tác vụ phức tạp
Ví dụ: refactor nhiều file, thay đổi luồng nghiệp vụ, database, xác thực, phân quyền, deploy hoặc debug khó.
- Khảo sát kiến trúc và các phụ thuộc trước khi sửa.
- Xác định rủi ro, phạm vi ảnh hưởng và cách kiểm chứng.
- Với thao tác phá hủy dữ liệu, migration, deploy hoặc thay đổi có thể gây downtime, phải kiểm tra điều kiện an toàn trước khi thực thi; không tự ý thực hiện hành động không thể đảo ngược khi chưa được cho phép.

## 3. Quy tắc dùng công cụ MCP

Ưu tiên công cụ chuyên biệt khi công cụ đó khả dụng và phù hợp với nhiệm vụ:

- `codebase-memory`: dùng `search_graph`, `search_code`, `query_graph` để tìm class, method, route, model, biến và quan hệ trong codebase; sau thay đổi quan trọng, dùng `detect_changes` nếu công cụ hỗ trợ.
- `figma`: đọc file/node/tokens khi người dùng cung cấp link Figma hoặc yêu cầu đối chiếu thiết kế.
- `sequential-thinking`: hỗ trợ phân tích logic nhiều bước hoặc debug phức tạp khi khả dụng.
- `memory`: truy xuất/lưu quy ước dự án khi cần và khi công cụ được cung cấp.
- `firecrawl`: tra cứu tài liệu web khi cần và khi công cụ được cung cấp.

### Nếu MCP không khả dụng
- Không được tuyên bố đã dùng hoặc đã xác minh bằng MCP nếu chưa thực sự gọi thành công.
- Nêu ngắn gọn công cụ nào không khả dụng nếu điều đó ảnh hưởng đến độ tin cậy.
- Dùng phương án dự phòng ít rủi ro nhất: đọc file liên quan, dùng tìm kiếm/IDE hoặc công cụ kiểm tra sẵn có. Chỉ đọc phạm vi cần thiết; tránh tải/đọc file lớn không liên quan.
- Không để yêu cầu bắt buộc dùng MCP làm bế tắc tác vụ khi có phương án thay thế an toàn.

Không chạy lệnh tìm kiếm mù quáng hoặc quét toàn bộ dự án nếu có thể định vị chính xác bằng công cụ chuyên biệt. Tuy nhiên, lệnh terminal phục vụ kiểm tra cú pháp, test, lint, git diff hoặc xác minh có mục tiêu vẫn được phép khi cần.

## 4. Bảo toàn kiến trúc và phạm vi thay đổi

- Trước khi sửa, xác định file, component, module, CSS và JS liên quan; ưu tiên mở rộng cách triển khai hiện có.
- Không tự ý thay framework, thư viện, quy ước đặt tên, cấu trúc thư mục, router, luồng dữ liệu hoặc kiến trúc Nasanic.
- Chỉ sửa các file cần thiết để đáp ứng yêu cầu. Không refactor ngoài phạm vi nếu không cần thiết.
- Trước khi sửa CSS global, component dùng chung, helper, route, model hoặc logic backend, hãy đánh giá ảnh hưởng đến các trang/chức năng đang sử dụng.
- Không xóa code, dữ liệu, cấu hình hoặc migration hiện có chỉ vì có vẻ không cần thiết khi chưa xác minh nơi sử dụng.
- Không đưa secret, token, thông tin đăng nhập hoặc dữ liệu cá nhân vào log, source code, commit hay câu trả lời.
- Không tạo placeholder như `TODO`, `...` hoặc code giả trong phần được yêu cầu hoàn thiện, trừ khi người dùng yêu cầu bản phác thảo.

## 5. Thứ tự ưu tiên khi có xung đột

Áp dụng theo thứ tự:
1. Yêu cầu trực tiếp, rõ ràng của người dùng trong lượt hiện tại.
2. Quy tắc an toàn, bảo mật và bảo toàn dữ liệu.
3. Quy chuẩn bắt buộc của dự án trong `AGENTS.md` và skill đã kích hoạt.
4. Kiến trúc, convention và hành vi hiện có của dự án.
5. Chi tiết thiết kế Figma.
6. Sở thích hoặc thông lệ chung của agent.

Nếu hai yêu cầu cùng cấp mâu thuẫn hoặc không thể thỏa mãn đồng thời:
- Không âm thầm bỏ qua một quy tắc.
- Tìm giải pháp tương thích với các ràng buộc cao hơn.
- Nếu vẫn không thể giải quyết, hỏi người dùng khi quyết định ảnh hưởng đáng kể; nếu có thể tiếp tục an toàn, nêu rõ giả định và giới hạn.

## 6. Quy trình bắt buộc cho tác vụ giao diện

Khi có liên quan đến Figma/Nasanic, làm theo quy trình:

1. **Đọc yêu cầu**: xác định trang/section, trạng thái giao diện, responsive và tương tác cần làm.
2. **Đọc nguồn thiết kế**: nếu có link Figma và MCP khả dụng, đọc đúng node/frame, tokens, typography, spacing, màu, gradient, ảnh và trạng thái tương tác. Không tự nhận đã đọc nếu chưa truy xuất được.
3. **Khảo sát codebase**: xác định Blade/view, CSS, JS, helper ảnh, thư viện và component hiện có; dùng `codebase-memory` nếu khả dụng.
4. **Lập phạm vi sửa**: tái sử dụng cấu trúc có sẵn; xác định file cần chỉnh; tránh thay đổi ngoài yêu cầu.
5. **Triển khai theo skill**: tuân thủ `figma-to-nasanic-ui`, đặc biệt quy tắc CSS, breakpoint, Blade, `render_image`, form/validation và thư viện.
6. **Tự kiểm tra**: rà soát diff, CSS bị cấm, responsive, dữ liệu động, liên kết, form và tương tác liên quan.
7. **Xác minh và báo cáo**: chạy kiểm tra khả dụng; nếu có thể, xem giao diện ở các kích thước mục tiêu. Phân biệt rõ kiểm tra đã chạy với kiểm tra chưa thể chạy.

Nếu không có link Figma hoặc không thể truy cập Figma, làm việc dựa trên nguồn thực tế sẵn có và không bịa thông số thiết kế.

## 7. Kiểm tra sau chỉnh sửa

Tối thiểu, tùy phạm vi thay đổi:
- Xem `git diff` hoặc diff tương đương để phát hiện sửa nhầm, xóa nhầm và thay đổi ngoài phạm vi.
- Kiểm tra cú pháp/lint/test liên quan nếu dự án có công cụ tương ứng.
- Với giao diện: kiểm tra desktop và các breakpoint dự án; tìm lỗi tràn ngang, chồng lấn, co rút bất thường, font sai, khoảng cách sai và ảnh bị cắt ngoài ý muốn.
- Với JS: kiểm tra selector, khởi tạo thư viện, sự kiện và trường hợp không có dữ liệu.
- Với Blade/form: kiểm tra dữ liệu null, name/id, method/action, CSRF, validation và cấu hình bảo vệ chống bot theo chuẩn dự án.
- Sau thay đổi quan trọng, gọi `detect_changes` để đồng bộ chỉ mục nếu MCP hỗ trợ.

Không được tuyên bố “đã test”, “pixel-perfect” hoặc “đã xác minh” nếu chưa thực hiện phép kiểm tra tương ứng. Nếu không thể chạy ứng dụng/trình duyệt, báo rõ giới hạn đó.

## 8. Cách phản hồi

- Tập trung vào kết quả, súc tích.
- Nêu file đã sửa và thay đổi chính.
- Nêu kiểm tra đã thực hiện và kết quả; chỉ liệt kê giới hạn thực sự ảnh hưởng.
- Không giải thích dài dòng cho chỉnh sửa đơn giản.
