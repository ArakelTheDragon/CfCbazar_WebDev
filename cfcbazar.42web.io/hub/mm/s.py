# server.py
import http.server
import socketserver
import mimetypes

# Teach Python to serve .wasm correctly
mimetypes.add_type('application/wasm', '.wasm')

class CSPHandler(http.server.SimpleHTTPRequestHandler):
    def end_headers(self):
        # This header allows Emscripten’s new Function() eval calls
        self.send_header(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-eval';"
        )
        super().end_headers()

if __name__ == '__main__':
    PORT = 8080
    # Serve the current directory (so that ./mm/index.html resolves)
    Handler = CSPHandler
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        print(f"Serving at http://localhost:{PORT}")
        httpd.serve_forever()

