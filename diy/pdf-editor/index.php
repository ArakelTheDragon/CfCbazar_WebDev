<?php
// /diy/pdf-editor/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Handle upload BEFORE any HTML output -----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {

    $file = $_FILES['pdf'];

    if ($file['size'] > 20 * 1024 * 1024) {
        die("File too large. Max 20MB.");
    }

    if ($file['error'] === 0) {

        $pages = json_decode($_POST['pages'], true);

        require_once __DIR__ . '/fpdf.php';
        $pdf = new FPDF();

        foreach ($pages as $page) {
            $img         = $page['image'];
            $annotations = $page['annotations'];

            // Decode base64 PNG
            $imgData = base64_decode(str_replace('data:image/png;base64,', '', $img));
            $tmpImg  = tempnam(sys_get_temp_dir(), "pg") . ".png";
            file_put_contents($tmpImg, $imgData);

            list($w, $h) = getimagesize($tmpImg);

            $pdf->AddPage("P", [$w, $h]);
            $pdf->Image($tmpImg, 0, 0, $w, $h);

            foreach ($annotations as $a) {
                if ($a['type'] === 'text') {
                    $pdf->SetFont('Helvetica', '', 16);
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->SetXY($a['x'], $a['y']);
                    $pdf->Write(8, $a['value']);
                }
                if ($a['type'] === 'rect') {
                    $pdf->SetDrawColor(255, 0, 0);
                    $pdf->Rect($a['x'], $a['y'], $a['w'], $a['h']);
                }
            }

            unlink($tmpImg);
        }

        $target = "edited.pdf";
        $pdf->Output($target, "F");

        header("Content-Disposition: attachment; filename=\"$target\"");
        header("Content-Type: application/pdf");
        header("Content-Length: " . filesize($target));

        readfile($target);
        unlink($target);
        exit;
    }
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/pdf-editor/index.php";
$parent_url = "/diy/pdf-editor";
$title      = "Online PDF Editor — CfCbazar DIY";
$template   = "Generic";
$meta_title = "Online PDF Editor — CfCbazar DIY";
$meta_desc  = "Add text or shapes to your PDF files online. Max 20MB.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container">

        <h1 class="page-title">Online PDF Editor</h1>
        <p class="subtitle">Add text or shapes to your PDF. Max 20MB.</p>

        <div class="card">
            <form id="pdfForm" method="POST" enctype="multipart/form-data">

                <div class="input-group">
                    <label>Select PDF</label>
                    <input type="file" name="pdf" id="pdfInput" accept="application/pdf" required>
                </div>

                <div class="input-group">
                    <label>Add Annotation</label>
                    <select id="annotationType">
                        <option value="text">Text</option>
                        <option value="rect">Rectangle</option>
                    </select>
                </div>

                <div class="input-group">
                    <button type="button" class="btn" id="undoBtn">Undo</button>
                    <button type="button" class="btn" id="redoBtn">Redo</button>
                    <button type="button" class="btn" id="deleteBtn">Delete Mode</button>
                </div>

                <div id="pagesContainer"></div>

                <input type="hidden" name="pages" id="pagesField">

                <div class="progress-container" id="progressBox" style="display:none;width:100%;background:#eee;border-radius:10px;margin-top:15px;">
                    <div class="progress-bar" id="progressBar" style="height:18px;width:0%;background:#28a745;border-radius:10px;transition:width 0.2s;"></div>
                </div>
                <div class="progress-text" id="progressText" style="margin-top:5px;font-size:0.9rem;text-align:center;"></div>

                <div class="input-group">
                    <button type="submit" class="btn">Download Edited PDF</button>
                </div>

            </form>
        </div>

    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

<script>
let pages = [];
let pdfDoc = null;
let undoStacks = [];
let redoStacks = [];
let deleteMode = false;
let currentPageIndex = 0;

function drawAnnotation(ctx, a) {
    if (a.type === "text") {
        ctx.fillStyle = "red";
        ctx.font = "16px Helvetica";
        ctx.fillText(a.value, a.x, a.y);
    }
    if (a.type === "rect") {
        ctx.strokeStyle = "red";
        ctx.strokeRect(a.x, a.y, a.w, a.h);
    }
}

function addAnnotation(pageIndex, annotation, ctx) {
    undoStacks[pageIndex].push(JSON.stringify(pages[pageIndex].annotations));
    redoStacks[pageIndex] = [];
    pages[pageIndex].annotations.push(annotation);
    drawAnnotation(ctx, annotation);
}

function redrawPage(pageIndex) {
    const canvas = pages[pageIndex].canvas;
    const ctx = canvas.getContext("2d");

    pdfDoc.getPage(pageIndex + 1).then(function(page) {
        const viewport = page.getViewport({ scale: 1.5 });
        page.render({ canvasContext: ctx, viewport }).promise.then(() => {
            pages[pageIndex].annotations.forEach(a => drawAnnotation(ctx, a));
        });
    });
}

document.getElementById("pdfInput").addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const typedarray = new Uint8Array(e.target.result);

        pdfjsLib.getDocument(typedarray).promise.then(async function(pdf) {
            pdfDoc = pdf;
            const container = document.getElementById("pagesContainer");
            container.innerHTML = "";
            pages = [];
            undoStacks = [];
            redoStacks = [];
            currentPageIndex = 0;

            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const viewport = page.getViewport({ scale: 1.5 });

                const canvas = document.createElement("canvas");
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.className = "pdf-canvas";
                canvas.style.border = "1px solid #ccc";
                canvas.style.width = "100%";
                canvas.style.marginTop = "15px";

                const ctx = canvas.getContext("2d");
                await page.render({ canvasContext: ctx, viewport }).promise;

                container.appendChild(canvas);

                pages.push({ canvas, annotations: [] });
                undoStacks[i - 1] = [];
                redoStacks[i - 1] = [];

                canvas.addEventListener("click", function(e) {
                    const rect = canvas.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const pageIndex = i - 1;
                    currentPageIndex = pageIndex;

                    const type = document.getElementById("annotationType").value;
                    const ctxLocal = canvas.getContext("2d");

                    if (deleteMode) {
                        const ann = pages[pageIndex].annotations;

                        for (let k = ann.length - 1; k >= 0; k--) {
                            const a = ann[k];

                            if (a.type === "text") {
                                if (Math.abs(a.x - x) < 50 && Math.abs(a.y - y) < 20) {
                                    undoStacks[pageIndex].push(JSON.stringify(ann));
                                    redoStacks[pageIndex] = [];
                                    ann.splice(k, 1);
                                    redrawPage(pageIndex);
                                    return;
                                }
                            }

                            if (a.type === "rect") {
                                if (x >= a.x && x <= a.x + a.w && y >= a.y && y <= a.y + a.h) {
                                    undoStacks[pageIndex].push(JSON.stringify(ann));
                                    redoStacks[pageIndex] = [];
                                    ann.splice(k, 1);
                                    redrawPage(pageIndex);
                                    return;
                                }
                            }
                        }
                        return;
                    }

                    if (type === "text") {
                        const text = prompt("Enter text:");
                        if (!text) return;

                        addAnnotation(pageIndex, { type: "text", x, y, value: text }, ctxLocal);
                    }

                    if (type === "rect") {
                        const w = 120, h = 60;
                        addAnnotation(pageIndex, { type: "rect", x, y, w, h }, ctxLocal);
                    }
                });
            }
        });
    };
    reader.readAsArrayBuffer(file);
});

document.getElementById("undoBtn").addEventListener("click", function() {
    const pageIndex = currentPageIndex;
    if (!pages[pageIndex]) return;
    if (undoStacks[pageIndex].length === 0) return;

    const current = JSON.stringify(pages[pageIndex].annotations);
    redoStacks[pageIndex].push(current);

    const previous = undoStacks[pageIndex].pop();
    pages[pageIndex].annotations = JSON.parse(previous);

    redrawPage(pageIndex);
});

document.getElementById("redoBtn").addEventListener("click", function() {
    const pageIndex = currentPageIndex;
    if (!pages[pageIndex]) return;
    if (redoStacks[pageIndex].length === 0) return;

    const current = JSON.stringify(pages[pageIndex].annotations);
    undoStacks[pageIndex].push(current);

    const next = redoStacks[pageIndex].pop();
    pages[pageIndex].annotations = JSON.parse(next);

    redrawPage(pageIndex);
});

document.getElementById("deleteBtn").addEventListener("click", function() {
    deleteMode = !deleteMode;
    this.textContent = deleteMode ? "Exit Delete Mode" : "Delete Mode";
});

document.getElementById("pdfForm").addEventListener("submit", function(e) {
    const file = document.getElementById("pdfInput").files[0];
    if (!file) return;

    if (file.size > 20 * 1024 * 1024) {
        alert("File too large. Maximum allowed is 20MB.");
        e.preventDefault();
        return;
    }

    const progressBox = document.getElementById("progressBox");
    const progressBar = document.getElementById("progressBar");
    const progressText = document.getElementById("progressText");

    progressBox.style.display = "block";

    e.preventDefault();

    const pagesData = pages.map(p => ({
        image: p.canvas.toDataURL("image/png"),
        annotations: p.annotations
    }));

    document.getElementById("pagesField").value = JSON.stringify(pagesData);

    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener("progress", function(event) {
        if (event.lengthComputable) {
            const percent = (event.loaded / event.total) * 100;
            const mbLoaded = (event.loaded / (1024 * 1024)).toFixed(2);
            const mbTotal = (event.total / (1024 * 1024)).toFixed(2);

            progressBar.style.width = percent + "%";
            progressText.textContent = `${mbLoaded} MB / ${mbTotal} MB`;
        }
    });

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const blob = new Blob([xhr.response], { type: "application/pdf" });
            const link = document.createElement("a");
            link.href = window.URL.createObjectURL(blob);
            link.download = "edited.pdf";
            link.click();
        }
    };

    xhr.open("POST", window.location.href);
    xhr.responseType = "arraybuffer";
    xhr.send(formData);
});
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pdf-editor",
    "PDF Editor Source Code"
);
include_footer();
close_database();
?>
