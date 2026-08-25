# Multi-Model Execution & Development Rules

## 1. Tối ưu hóa theo độ phức tạp tác vụ
- **Tác vụ đơn giản / UI / File đơn lẻ (HTML, CSS, text, chỉnh sửa nhỏ)**: Thực thi chỉnh sửa trực tiếp ngay lập tức, phản hồi ngắn gọn, không giải thích dông dài, không cần lập kế hoạch rườm rà.
- **Tác vụ phức tạp / Logic hệ thống (Deploy, Database, Refactor đa file, Debug)**: Cho phép suy luận kỹ lưỡng, kiểm tra an toàn dữ liệu trước khi thực thi để đảm bảo độ chính xác tuyệt đối.

## 2. Ưu tiên sử dụng MCP Tools
- **Luôn ưu tiên tận dụng các công cụ MCP khả dụng** để xử lý tác vụ:
  - Sử dụng `sequential-thinking` cho các bài toán phân tích logic phức tạp, debug sâu.
  - Sử dụng `codebase-memory` / `memory` để truy xuất, ghi nhớ ngữ cảnh dự án và quan hệ kiến trúc.
  - Sử dụng `figma` để đọc design/tokens khi làm việc với giao diện Figma.
  - Sử dụng `firecrawl` khi cần cào dữ liệu, tra cứu tài liệu web hoặc tài liệu kỹ thuật bên ngoài.

## 3. Quy chuẩn thực thi Code & Terminal
- Thao tác trực tiếp lên file, ưu tiên code hoàn chỉnh, không dùng placeholder (TODO/...).
- Tránh chạy vòng lặp terminal không cần thiết trừ khi tác vụ yêu cầu xác minh lỗi, test cú pháp hoặc chạy git commit/push.
- Phản hồi tập trung vào kết quả công việc, súc tích và dễ theo dõi.

## 4. Strict Model Lock
- Sử dụng duy nhất model đang được người dùng chọn ở giao diện ngoài cho mọi tác vụ.
- Không tự ý gọi sub-agents thuộc dòng model khác cấu hình hiện tại để tránh phân mảnh ngữ cảnh và tối ưu chi phí token.