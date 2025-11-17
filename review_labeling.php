<?php
$imageDir = './dataset/images/train';
$labelDir = './dataset/labels/train';

// Get image files
$images = array_values(array_filter(scandir($imageDir), function($f) {
    return preg_match('/\.(jpg|jpeg|png|bmp)$/i', $f);
}));
?>

<!DOCTYPE html>
<html>
<head>
    <title>YOLO Annotation Viewer & Editor</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        canvas { border: 1px solid #ccc; margin-top: 10px; max-width: 100%; height: auto; }
        .controls { margin-bottom: 20px; }
        .info { margin-top: 10px; }
        .box-info { font-size: 13px; margin: 2px 0; }
        .filename { font-weight: bold; font-size: 16px; margin-top: 10px; }
    </style>
</head>
<body>
<h1>YOLO Annotation Viewer & Class Editor</h1>

<div class="controls">
    <button onclick="showPrevious()">⬅️ Previous</button>
    <button onclick="showNext()">Next ➡️</button>
</div>

<div id="viewer">
    <div class="filename" id="filename"></div>
    <img id="image" style="display:none;" onload="draw()">
    <canvas id="canvas"></canvas>
    <div class="info" id="info"></div>
</div>

<div style="margin-top: 20px;">
    <button onclick="saveToFile()">💾 Save Annotations to File</button>
    <span id="saveStatus" style="margin-left: 10px; font-weight: bold;"></span>
</div>

<!-- Edit class form -->
<div id="editBoxForm" style="display:none; margin-top: 20px; border: 1px solid #ccc; padding: 10px;">
    <h3>Edit Class ID</h3>
    <form onsubmit="saveBoxEdit(event)">
        <label>Class:
            <select id="editClass">
                <option value="0">0 - Down</option>
                <option value="1">1 - Left</option>
                <option value="2">2 - Right</option>
                <option value="3">3 - Up</option>
            </select>
        </label><br><br>
        <button type="submit">💾 Save</button>
        <button type="button" onclick="cancelEdit()">Cancel</button>
    </form>
</div>

<script>
// Image + Label data from PHP
const images = <?php echo json_encode($images); ?>;
const labelData = {};
let currentIndex = 0;
let selectedBoxIndex = null;

// Load all label data from PHP
<?php foreach ($images as $imageFile):
    $labelFile = $labelDir . '/' . pathinfo($imageFile, PATHINFO_FILENAME) . '.txt';
    if (!file_exists($labelFile)) {
        echo "labelData['$imageFile'] = [];\n";
        continue;
    }
    $lines = file($labelFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $boxes = [];
    foreach ($lines as $line) {
        $parts = explode(' ', $line);
        if (count($parts) !== 5) continue;
        list($class, $x, $y, $w, $h) = $parts;
        $boxes[] = [
            'class' => (int)$class,
            'x' => (float)$x,
            'y' => (float)$y,
            'w' => (float)$w,
            'h' => (float)$h
        ];
    }
    echo "labelData['$imageFile'] = " . json_encode($boxes) . ";\n";
endforeach; ?>

function getColor(classId) {
    const colors = [
        '#e6194b', '#3cb44b', '#ffe119', '#4363d8',
        '#f58231', '#911eb4', '#46f0f0', '#f032e6',
        '#bcf60c', '#fabebe', '#008080', '#e6beff',
        '#9a6324', '#fffac8', '#800000', '#aaffc3'
    ];
    return colors[classId % colors.length];
}

function draw() {
    const img = document.getElementById('image');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const filename = images[currentIndex];
    const boxes = labelData[filename] || [];

    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0);

    const infoDiv = document.getElementById('info');
    infoDiv.innerHTML = '';

    boxes.forEach((box, i) => {
        const color = getColor(box.class);
        const x = box.x * canvas.width - (box.w * canvas.width) / 2;
        const y = box.y * canvas.height - (box.h * canvas.height) / 2;
        const w = box.w * canvas.width;
        const h = box.h * canvas.height;

        ctx.strokeStyle = (i === selectedBoxIndex) ? 'black' : color;
        ctx.lineWidth = (i === selectedBoxIndex) ? 3 : 2;
        ctx.strokeRect(x, y, w, h);
        ctx.fillStyle = color + '33';
        ctx.fillRect(x, y, w, h);

        const CLASS_DATA = {0: "Down", 1: "Left", 2: "Right", 3: "Up"};
        const label = `${CLASS_DATA[box.class] || 'Class ' + box.class}`;
        ctx.fillStyle = color;
        ctx.font = '14px Arial';
        ctx.fillText(label, x, y - 10);

        const boxInfo = document.createElement('div');
        boxInfo.className = 'box-info';
        boxInfo.innerHTML = `📦 <b>${label}</b> | x: ${x.toFixed(1)}, y: ${y.toFixed(1)}, w: ${w.toFixed(1)}, h: ${h.toFixed(1)} 
        <button onclick="editBox(${i})">✏️ Edit</button>`;
        infoDiv.appendChild(boxInfo);
    });
}

function loadImage(index) {
    if (index < 0 || index >= images.length) return;
    currentIndex = index;
    const filename = images[index];
    const labelCount = (labelData[filename] || []).length;
    document.getElementById('filename').innerText =
        `🖼️ ${filename} (${currentIndex + 1}/${images.length}) | 📦 ${labelCount} annotation${labelCount !== 1 ? 's' : ''}`;
    const img = document.getElementById('image');
    img.src = '<?php echo $imageDir; ?>/' + filename;
}

function showNext() {
    if (currentIndex < images.length - 1) {
        loadImage(currentIndex + 1);
    }
}

function showPrevious() {
    if (currentIndex > 0) {
        loadImage(currentIndex - 1);
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowRight') showNext();
    if (e.key === 'ArrowLeft') showPrevious();
});

// Edit class ID
function editBox(index) {
    const filename = images[currentIndex];
    const box = labelData[filename][index];

    selectedBoxIndex = index;
    document.getElementById('editClass').value = box.class;
    document.getElementById('editBoxForm').style.display = 'block';
    draw();
}

function saveBoxEdit(e) {
    e.preventDefault();
    const filename = images[currentIndex];
    const box = labelData[filename][selectedBoxIndex];
    box.class = parseInt(document.getElementById('editClass').value);

    selectedBoxIndex = null;
    document.getElementById('editBoxForm').style.display = 'none';
    draw();
}

function cancelEdit() {
    selectedBoxIndex = null;
    document.getElementById('editBoxForm').style.display = 'none';
    draw();
}

// Save to server
function saveToFile() {
    const filename = images[currentIndex];
    const boxes = labelData[filename] || [];

    fetch('save_labels.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename, boxes })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('saveStatus').innerText = "✅ Saved!";
            setTimeout(() => {
                document.getElementById('saveStatus').innerText = "";
            }, 2000);
        } else {
            document.getElementById('saveStatus').innerText = "❌ Failed to save.";
        }
    })
    .catch(err => {
        console.error(err);
        document.getElementById('saveStatus').innerText = "❌ Error saving.";
    });
}

// Load first image
window.onload = () => {
    loadImage(0);
};
</script>
</body>
</html>
