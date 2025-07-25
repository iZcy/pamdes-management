<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pembacaan Meteran - PAMDes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-blue-600 text-white p-4 shadow-lg">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <i class="fas fa-tachometer-alt text-2xl"></i>
                <div>
                    <h1 class="text-xl font-bold">Pembacaan Meteran</h1>
                    <p class="text-blue-200 text-sm">Operator: {{ auth()->user()->name }}</p>
                </div>
            </div>
            <div class="text-right">
                @if($activePeriod)
                    <p class="text-sm">Periode: {{ $activePeriod->month }}/{{ $activePeriod->year }}</p>
                    <p class="text-blue-200 text-xs">{{ $activePeriod->name }}</p>
                @endif
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto p-4 max-w-4xl">
        @if(isset($error))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span>{{ $error }}</span>
                </div>
            </div>
        @else
            <!-- Testing Mode Banner -->
            <div class="bg-gradient-to-r from-green-100 to-red-100 border-l-4 border-green-500 p-4 mb-6 rounded-lg shadow-sm">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-th text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-green-800 mb-2">🧪 TESTING MODE: Individual Digit Grid Recognition</h3>
                        <div class="text-sm text-green-700 space-y-1">
                            <p><strong>Method:</strong> Extract 7 individual digit regions from meter reading for separate OCR processing</p>
                            <p><strong>Grid System:</strong> 4 green boxes (black digits) + separator + 3 red boxes (red digits)</p>
                            <p><strong>Per-Digit OCR:</strong> Each digit processed independently to avoid separator confusion</p>
                            <p><strong>Benefits:</strong> Higher accuracy, bypasses digit separators, focused single-digit recognition</p>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="bg-green-200 text-green-800 px-2 py-1 rounded">7-Digit Grid ✓</span>
                            <span class="bg-green-200 text-green-800 px-2 py-1 rounded">Individual OCR ✓</span>
                            <span class="bg-green-200 text-green-800 px-2 py-1 rounded">Separator Bypass ✓</span>
                            <span class="bg-green-200 text-green-800 px-2 py-1 rounded">Digit Analysis ✓</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Camera and Reading Form -->
            <div id="reading-form" class="bg-white rounded-lg shadow-md p-6">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-camera mr-2 text-blue-600"></i>
                        Scan Meteran untuk Testing
                    </h2>
                    <p class="text-gray-600 text-sm mt-1">Focus pada upper box untuk mendeteksi nilai meteran</p>
                </div>


                <form id="meter-reading-form" enctype="multipart/form-data">
                    @csrf
                    
                    <!-- Camera Section -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-camera mr-1"></i> Foto Meteran
                        </label>
                        
                        <!-- Camera Preview with Positioning Guides -->
                        <div class="relative bg-gray-100 rounded-lg overflow-hidden mb-4" style="aspect-ratio: 4/3; width: 100%; max-width: 800px;">
                            <video id="camera-preview" class="w-full h-full object-cover hidden"></video>
                            <canvas id="photo-canvas" class="w-full h-full object-cover hidden"></canvas>
                            
                            <!-- Camera Placeholder -->
                            <div id="camera-placeholder" class="w-full h-full flex items-center justify-center text-gray-400">
                                <div class="text-center">
                                    <i class="fas fa-camera text-4xl mb-2"></i>
                                    <p>Klik tombol di bawah untuk mengambil foto</p>
                                </div>
                            </div>
                            
                            <!-- Digit Grid Overlay -->
                            <div id="number-boxes-overlay" class="absolute inset-0 hidden" style="pointer-events: none;">
                                
                                <!-- 7-Digit Grid for Meter Reading -->
                                <div class="absolute" style="top: 15%; left: 20%; width: 60%; height: 15%;">
                                    <div class="flex w-full h-full gap-0.5">
                                        <!-- Digit 1 -->
                                        <div class="flex-1 border-2 border-green-500 bg-green-50 bg-opacity-40 flex items-center justify-center">
                                            <div class="text-xs font-bold text-green-700" id="digit-1-status">1</div>
                                        </div>
                                        <!-- Digit 2 -->
                                        <div class="flex-1 border-2 border-green-500 bg-green-50 bg-opacity-40 flex items-center justify-center">
                                            <div class="text-xs font-bold text-green-700" id="digit-2-status">2</div>
                                        </div>
                                        <!-- Digit 3 -->
                                        <div class="flex-1 border-2 border-green-500 bg-green-50 bg-opacity-40 flex items-center justify-center">
                                            <div class="text-xs font-bold text-green-700" id="digit-3-status">3</div>
                                        </div>
                                        <!-- Digit 4 -->
                                        <div class="flex-1 border-2 border-green-500 bg-green-50 bg-opacity-40 flex items-center justify-center">
                                            <div class="text-xs font-bold text-green-700" id="digit-4-status">4</div>
                                        </div>
                                        <!-- Separator -->
                                        <div class="w-1 border-l-2 border-yellow-400 bg-yellow-200 bg-opacity-60"></div>
                                        <!-- Digit 5 (Red) -->
                                        <div class="flex-1 border-2 border-red-500 bg-red-50 bg-opacity-40 flex items-center justify-center">
                                            <div class="text-xs font-bold text-red-700" id="digit-5-status">5</div>
                                        </div>
                                        <!-- Digit 6 (Red) -->
                                        <div class="flex-1 border-2 border-red-500 bg-red-50 bg-opacity-40 flex items-center justify-center">
                                            <div class="text-xs font-bold text-red-700" id="digit-6-status">6</div>
                                        </div>
                                        <!-- Digit 7 (Red) -->
                                        <div class="flex-1 border-2 border-red-500 bg-red-50 bg-opacity-40 flex items-center justify-center">
                                            <div class="text-xs font-bold text-red-700" id="digit-7-status">7</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Customer ID Box (8-digit at bottom with large gap) -->
                                <div class="absolute" style="top: 85%; left: 30%; width: 40%; height: 12%;">
                                    <div id="customer-id-box" class="w-full h-full border-4 border-blue-400 bg-blue-50 bg-opacity-30 rounded-lg flex items-center justify-center">
                                        <div class="text-center">
                                            <div class="text-sm text-blue-700 font-bold">CUSTOMER ID</div>
                                            <div class="text-xs text-blue-600">8 digits (21233920)</div>
                                        </div>
                                    </div>
                                </div>

                                
                                
                            </div>
                            
                            <img id="captured-photo" class="w-full h-full object-cover hidden">
                        </div>
                        
                        <!-- Status Display (Outside Camera View) -->
                        <div class="mt-2 text-center">
                            <div id="boxes-instruction" class="text-sm text-gray-600 mb-2">🔍 Aligning digits in boxes</div>
                        </div>
                        
                        <!-- Manual Capture Button (Outside Camera View) -->
                        <div class="text-center">
                            <button id="manual-capture-btn" 
                                    class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 shadow-lg">
                                <i class="fas fa-camera mr-2"></i>MANUAL CAPTURE
                            </button>
                        </div>

                        <!-- Testing Info -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                            <h4 class="font-semibold text-green-800 mb-2 flex items-center">
                                <i class="fas fa-th mr-2"></i>Individual Digit Grid Capture
                            </h4>
                            <p class="text-sm text-green-700">Extracts and processes each digit separately to avoid separator confusion:</p>
                            <div class="mt-2 text-xs text-green-600 space-y-1">
                                🟢 <strong>Digits 1-4:</strong> Black digits in green boxes (main reading)<br>
                                🔴 <strong>Digits 5-7:</strong> Red digits in red boxes (decimal part)<br>
                                📊 <strong>Grid System:</strong> 7 individual regions extracted from aligned meter<br>
                                🔍 <strong>Per-Digit OCR:</strong> Each digit sent to OCR independently<br>
                                🎯 <strong>Alignment:</strong> Position each digit within its corresponding grid box<br>
                                📝 <strong>Result:</strong> Individual digit recognition results combined into full reading
                            </div>
                        </div>

                        <!-- Webcam Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-video mr-1"></i> Pilih Kamera
                            </label>
                            <select id="webcam-select" class="w-full p-2 border border-gray-300 rounded-lg bg-white">
                                <option value="">Memuat daftar kamera...</option>
                            </select>
                        </div>

                        <!-- Camera Controls -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button type="button" id="start-camera" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-video mr-2"></i> Buka Kamera
                            </button>
                            <button type="button" id="retake-photo" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 hidden">
                                <i class="fas fa-redo mr-2"></i> Foto Ulang
                            </button>
                            <button type="button" id="backup-capture-btn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700" onclick="manualCapture()">
                                <i class="fas fa-camera mr-2"></i> CAPTURE (Backup)
                            </button>
                        </div>


                        <!-- OCR Results Panel -->
                        <div id="ocr-results" class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4 hidden">
                            <h4 class="font-semibold text-purple-800 mb-2">
                                <i class="fas fa-robot mr-2"></i>Hasil Pembacaan Otomatis
                            </h4>
                            <div id="ocr-loading" class="text-center py-4 hidden">
                                <i class="fas fa-spinner fa-spin text-purple-600 text-xl"></i>
                                <p class="text-purple-600 mt-2">Mendeteksi meteran dan membaca angka...</p>
                                <p class="text-xs text-purple-500 mt-1">Sistem sedang menganalisis posisi dan memutar meteran</p>
                            </div>
                            <div id="ocr-suggestions" class="space-y-2"></div>
                        </div>

                        <input type="file" id="meter-photo" name="meter_photo" accept="image/*" class="hidden">
                    </div>


                    <!-- OCR Values Display Section -->
                    <div id="ocr-values-display" class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-lg p-6 mb-6 hidden">
                        <div class="text-center mb-4">
                            <h3 class="text-xl font-bold text-blue-800 mb-2">
                                <i class="fas fa-robot mr-2"></i>Hasil Pembacaan OCR
                            </h3>
                            <p class="text-sm text-blue-600">4 nilai utama yang berhasil dibaca dari meteran</p>
                        </div>
                        
                        <div class="grid md:grid-cols-2 gap-4 mb-4">
                            <!-- Full Reading (7-digit) -->
                            <div class="bg-white rounded-lg p-6 border-2 border-green-300 shadow-md">
                                <div class="text-center">
                                    <div class="text-lg font-medium text-green-700 mb-2">
                                        <i class="fas fa-eye mr-1"></i>Full Reading
                                    </div>
                                    <div id="display-full-reading" class="text-4xl font-mono font-bold text-green-800 mb-2">-</div>
                                    <div class="text-sm text-gray-600">7 digit (4 hitam + 3 merah)</div>
                                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                        <div class="bg-gray-100 p-2 rounded">
                                            <div class="font-bold text-gray-700">Normal (4)</div>
                                            <div id="display-normal-value" class="font-mono text-blue-600">-</div>
                                        </div>
                                        <div class="bg-gray-100 p-2 rounded">
                                            <div class="font-bold text-gray-700">Precise (3)</div>
                                            <div id="display-precise-value" class="font-mono text-red-600">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer ID -->
                            <div class="bg-white rounded-lg p-6 border-2 border-blue-300 shadow-md">
                                <div class="text-center">
                                    <div class="text-lg font-medium text-blue-700 mb-2">
                                        <i class="fas fa-id-card mr-1"></i>Customer ID
                                    </div>
                                    <div id="display-customer-id" class="text-4xl font-mono font-bold text-blue-800 mb-2">-</div>
                                    <div class="text-sm text-gray-600">8 digit ID pelanggan</div>
                                </div>
                            </div>
                        </div>


                        <!-- Confidence and Method Info -->
                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                            <div class="flex justify-between items-center text-sm">
                                <div>
                                    <span class="text-gray-600">OCR Method:</span>
                                    <span id="display-method" class="font-mono font-bold text-blue-600 ml-1">-</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Confidence:</span>
                                    <span id="display-confidence" class="font-mono font-bold text-green-600 ml-1">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        <div class="text-center mt-4">
                            <button id="reset-ocr" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                                <i class="fas fa-redo mr-2"></i>Baca Meteran Lagi
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </main>

    <!-- Success Modal -->
    <div id="success-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="text-center">
                <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                <h3 class="text-lg font-semibold mb-2">Pembacaan Berhasil Disimpan!</h3>
                <div id="success-details" class="text-gray-600 mb-4"></div>
                <button id="close-modal" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let stream = null;
        let capturedPhotoBlob = null;
        let availableDevices = [];
        let selectedDeviceId = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeCameraControls();
            initializeForm();
            enumerateWebcams(); // Load available webcams first
            
            // Backup event listener for capture button
            setTimeout(() => {
                const captureBtn = document.getElementById('manual-capture-btn');
                if (captureBtn) {
                    console.log('Adding backup event listener to capture button');
                    captureBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Backup capture button clicked!');
                        manualCapture();
                    });
                } else {
                    console.error('Capture button still not found in backup setup!');
                }
            }, 1000);
        });


        function initializeCameraControls() {
            const startCameraBtn = document.getElementById('start-camera');
            const retakePhotoBtn = document.getElementById('retake-photo');
            const manualCaptureBtn = document.getElementById('manual-capture-btn');
            const webcamSelect = document.getElementById('webcam-select');

            if (startCameraBtn) {
                startCameraBtn.addEventListener('click', startCamera);
            }
            if (retakePhotoBtn) {
                retakePhotoBtn.addEventListener('click', retakePhoto);
            }
            if (manualCaptureBtn) {
                manualCaptureBtn.addEventListener('click', manualCapture);
                console.log('Manual capture button event listener added');
            } else {
                console.error('Manual capture button not found!');
            }
            if (webcamSelect) {
                webcamSelect.addEventListener('change', function() {
                    selectedDeviceId = this.value;
                    console.log('Selected camera:', selectedDeviceId);
                    // If camera is already running, restart with new device
                    if (stream) {
                        stopCamera();
                        setTimeout(() => startCamera(), 500);
                    }
                });
            }
        }

        async function enumerateWebcams() {
            try {
                // Request permissions first
                const tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
                tempStream.getTracks().forEach(track => track.stop());
                
                // Now enumerate devices
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                
                availableDevices = videoDevices;
                const webcamSelect = document.getElementById('webcam-select');
                
                // Clear existing options
                webcamSelect.innerHTML = '';
                
                if (videoDevices.length === 0) {
                    webcamSelect.innerHTML = '<option value="">Tidak ada kamera ditemukan</option>';
                    return;
                }
                
                // Add default option
                webcamSelect.innerHTML = '<option value="">Pilih kamera...</option>';
                
                // Add each video device
                videoDevices.forEach((device, index) => {
                    const option = document.createElement('option');
                    option.value = device.deviceId;
                    
                    // Create friendly name
                    let deviceName = device.label || `Kamera ${index + 1}`;
                    
                    // Highlight Iriun webcam
                    if (deviceName.toLowerCase().includes('iriun')) {
                        deviceName = `🎥 ${deviceName} (Iriun Webcam)`;
                    }
                    
                    option.textContent = deviceName;
                    webcamSelect.appendChild(option);
                });
                
                // Auto-select first device or Iriun if found
                const iriunDevice = videoDevices.find(device => 
                    device.label.toLowerCase().includes('iriun')
                );
                
                if (iriunDevice) {
                    selectedDeviceId = iriunDevice.deviceId;
                    webcamSelect.value = iriunDevice.deviceId;
                    console.log('Auto-selected Iriun webcam:', iriunDevice.label);
                } else {
                    selectedDeviceId = videoDevices[0].deviceId;
                    webcamSelect.value = videoDevices[0].deviceId;
                    console.log('Auto-selected first camera:', videoDevices[0].label);
                }
                
                // Auto-start camera after selection
                setTimeout(() => startCamera(), 1000);
                
            } catch (error) {
                console.error('Error enumerating webcams:', error);
                const webcamSelect = document.getElementById('webcam-select');
                webcamSelect.innerHTML = '<option value="">Error: Tidak dapat mengakses kamera</option>';
            }
        }

        async function startCamera() {
            try {
                // Build video constraints
                const videoConstraints = {};
                
                // If a specific device is selected, use it
                if (selectedDeviceId) {
                    videoConstraints.deviceId = { exact: selectedDeviceId };
                } else {
                    // Fallback to environment camera preference
                    videoConstraints.facingMode = { ideal: 'environment' };
                }
                
                // Add resolution preferences for better quality
                videoConstraints.width = { ideal: 1920 };
                videoConstraints.height = { ideal: 1080 };
                
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: videoConstraints
                });
                
                const video = document.getElementById('camera-preview');
                video.srcObject = stream;
                video.play();

                // Get the actual device info for logging
                const track = stream.getVideoTracks()[0];
                const settings = track.getSettings();
                const deviceLabel = availableDevices.find(d => d.deviceId === settings.deviceId)?.label || 'Unknown';
                
                console.log('CAMERA STARTED:', {
                    device: deviceLabel,
                    resolution: `${settings.width}x${settings.height}`,
                    deviceId: settings.deviceId
                });

                // Show video, hide placeholder
                document.getElementById('camera-placeholder').classList.add('hidden');
                video.classList.remove('hidden');
                
                // Show number detection boxes overlay
                document.getElementById('number-boxes-overlay').classList.remove('hidden');
                
                // Start number detection for visual feedback
                startNumberDetection();
                
                // Update buttons - hide start camera when active
                document.getElementById('start-camera').classList.add('hidden');
                
                // Show initial instruction
                document.getElementById('boxes-instruction').textContent = 'Position meter in upper box for best detection';
                
            } catch (error) {
                console.error('Error accessing camera:', error);
                alert('Tidak dapat mengakses kamera. Pastikan browser memiliki izin kamera.');
            }
        }

        function capturePhoto() {
            const video = document.getElementById('camera-preview');
            const canvas = document.getElementById('photo-canvas');
            const ctx = canvas.getContext('2d');

            // Set canvas size to match video
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Draw video frame to canvas
            ctx.drawImage(video, 0, 0);

            // Extract both box regions from the captured frame
            extractBoxRegions(canvas).then(boxData => {
                // Store the box data for OCR processing
                capturedPhotoBlob = boxData;
                
                // Show captured photo (full image for preview)
                canvas.toBlob(function(fullBlob) {
                    const img = document.getElementById('captured-photo');
                    img.src = URL.createObjectURL(fullBlob);
                    img.classList.remove('hidden');
                }, 'image/jpeg', 0.8);
                
                // Hide video
                video.classList.add('hidden');
                
                // Update buttons
                document.getElementById('retake-photo').classList.remove('hidden');
                
                // Stop camera
                stopCamera();

                // Automatically process OCR after capture
                setTimeout(() => {
                    showToast('🔍 Processing OCR on both box regions...', 'success', 2000);
                    processOCRBoxes();
                }, 500);
            });
        }

        async function extractBoxRegions(sourceCanvas) {
            const ctx = sourceCanvas.getContext('2d');
            const canvasWidth = sourceCanvas.width;
            const canvasHeight = sourceCanvas.height;
            
            // Define digit grid positions (7 individual digits + customer ID)
            const digitBoxes = [];
            
            // 7-Digit Grid for Meter Reading (matching the visual grid)
            const gridTop = 0.15;
            const gridLeft = 0.20;
            const gridWidth = 0.60;
            const gridHeight = 0.15;
            
            // Calculate individual digit box dimensions
            const digitWidth = gridWidth / 7.5; // 7 digits + 0.5 for separator
            const gapWidth = digitWidth * 0.5 / 6; // Distribute separator space
            
            for (let i = 0; i < 7; i++) {
                let digitLeft = gridLeft + (i * digitWidth);
                
                // Add separator space after 4th digit (index 3)
                if (i >= 4) {
                    digitLeft += digitWidth * 0.5; // Add separator width
                }
                
                digitBoxes.push({
                    id: `digit-${i + 1}`,
                    name: `Digit ${i + 1} ${i < 4 ? '(Black)' : '(Red)'}`,
                    top: gridTop,
                    left: digitLeft,
                    width: digitWidth,
                    height: gridHeight,
                    digitIndex: i + 1,
                    digitType: i < 4 ? 'black' : 'red'
                });
            }
            
            // Add customer ID box
            digitBoxes.push({
                id: 'customer-id',
                name: 'Customer ID',
                top: 0.85,
                left: 0.30,
                width: 0.40,
                height: 0.12,
                digitIndex: 0,
                digitType: 'customer'
            });
            
            
            const boxData = {
                boxes: [],
                timestamp: new Date().toISOString(),
                extractionMethod: 'individual-digits'
            };
            
            for (const box of digitBoxes) {
                // Calculate actual pixel coordinates
                const startX = Math.floor(box.left * canvasWidth);
                const startY = Math.floor(box.top * canvasHeight);
                const boxWidth = Math.floor(box.width * canvasWidth);
                const boxHeight = Math.floor(box.height * canvasHeight);
                
                // Create canvas for this digit region
                const digitCanvas = document.createElement('canvas');
                digitCanvas.width = boxWidth;
                digitCanvas.height = boxHeight;
                const digitCtx = digitCanvas.getContext('2d');
                
                // Extract the digit region
                digitCtx.drawImage(
                    sourceCanvas, 
                    startX, startY, boxWidth, boxHeight,  // Source rectangle
                    0, 0, boxWidth, boxHeight             // Destination rectangle
                );
                
                // Convert to blob
                const digitBlob = await new Promise(resolve => {
                    digitCanvas.toBlob(resolve, 'image/jpeg', 0.95); // Higher quality for single digits
                });
                
                boxData.boxes.push({
                    id: box.id,
                    name: box.name,
                    blob: digitBlob,
                    coordinates: { startX, startY, boxWidth, boxHeight },
                    size: digitBlob.size,
                    digitIndex: box.digitIndex,
                    digitType: box.digitType
                });
                
                console.log(`Extracted ${box.name}:`, {
                    id: box.id,
                    coordinates: { startX, startY, boxWidth, boxHeight },
                    size: digitBlob.size + ' bytes',
                    digitType: box.digitType
                });
            }
            
            return boxData;
        }

        function manualCapture() {
            console.log('Manual capture button clicked!');
            showToast('📸 Capturing both box regions...', 'success', 1500);
            capturePhoto();
        }

        function retakePhoto() {
            resetCameraUI();
            startCamera();
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        }

        function resetCameraUI() {
            document.getElementById('camera-preview').classList.add('hidden');
            document.getElementById('captured-photo').classList.add('hidden');
            document.getElementById('camera-placeholder').classList.remove('hidden');
            
            document.getElementById('start-camera').classList.remove('hidden');
            document.getElementById('retake-photo').classList.add('hidden');
            
            // Hide OCR results and number boxes overlay
            document.getElementById('ocr-results').classList.add('hidden');
            document.getElementById('number-boxes-overlay').classList.add('hidden');
            document.getElementById('ocr-values-display').classList.add('hidden');
            
            // Stop number detection
            stopNumberDetection();
            
            console.log('CAMERA UI RESET - Ready for new session');
            
            capturedPhotoBlob = null;
        }

        function initializeForm() {
            // Initialize reset OCR button
            document.getElementById('reset-ocr').addEventListener('click', function() {
                // Hide OCR display
                document.getElementById('ocr-values-display').classList.add('hidden');
                
                // Reset camera and UI
                resetCameraUI();
                
                // Clear OCR values
                clearOCRDisplay();
                
                // Show success message
                showToast('🔄 Siap untuk pembacaan meteran baru', 'success');
            });
        }

        function showSuccessModal(data) {
            const details = document.getElementById('success-details');
            details.innerHTML = `
                <p><strong>Pelanggan:</strong> ${data.customer_code} - ${data.customer_name}</p>
                <p><strong>Pembacaan Sebelumnya:</strong> ${data.previous_reading}</p>
                <p><strong>Pembacaan Saat Ini:</strong> ${data.current_reading}</p>
                <p><strong>Pemakaian:</strong> ${data.usage} m³</p>
                <p><strong>Tanggal:</strong> ${data.reading_date}</p>
            `;

            document.getElementById('success-modal').classList.remove('hidden');
            document.getElementById('success-modal').classList.add('flex');

            document.getElementById('close-modal').addEventListener('click', function() {
                document.getElementById('success-modal').classList.add('hidden');
                document.getElementById('success-modal').classList.remove('flex');
            });
        }



        function showToast(type, message, duration = 3000) {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white transform transition-transform duration-300 translate-x-full`;
            
            if (type === 'success') {
                toast.classList.add('bg-green-500');
                toast.innerHTML = `<i class="fas fa-check mr-2"></i>${message}`;
            } else if (type === 'error') {
                toast.classList.add('bg-red-500');
                toast.innerHTML = `<i class="fas fa-times mr-2"></i>${message}`;
            }

            document.body.appendChild(toast);
            
            // Slide in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);

            // Slide out and remove
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, duration);
        }

        // Detection system with auto-capture
        let positioningInterval = null;
        let autoCaptureEnabled = true;
        let digitDetectionResults = {}; // Track individual digit detection status
        let consecutiveGoodFrames = 0; // Count frames where all digits detected
        const REQUIRED_GOOD_FRAMES = 3; // Need 3 consecutive good frames before auto-capture

        function startNumberDetection() {
            // Check for numbers in boxes every 500ms
            positioningInterval = setInterval(() => {
                checkNumbersInBoxes();
            }, 500);
            
            // Initial check after camera initializes
            setTimeout(() => {
                checkNumbersInBoxes();
            }, 1000);
        }

        function stopNumberDetection() {
            if (positioningInterval) {
                clearInterval(positioningInterval);
                positioningInterval = null;
            }
        }

        function checkNumbersInBoxes() {
            const video = document.getElementById('camera-preview');
            if (!video || video.videoWidth === 0) {
                return;
            }
            
            try {
                // Create a canvas to analyze the video frame
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                
                // Draw current video frame to canvas
                ctx.drawImage(video, 0, 0);
                
                // Get image data for analysis
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                
                // Check individual digit positions for auto-capture
                const digitResults = checkIndividualDigits(imageData, canvas.width, canvas.height);
                
                // Check customer ID box
                const customerIdResult = checkCustomerIdBox(imageData, canvas.width, canvas.height);
                
                // Update visual feedback
                updateDigitVisualFeedback(digitResults);
                updateCustomerIdVisualFeedback(customerIdResult);
                
                // Check for auto-capture trigger
                checkAutoCaptureTrigger(digitResults);
                
            } catch (error) {
                console.error('Number detection error:', error);
            }
        }

        function checkIndividualDigits(imageData, canvasWidth, canvasHeight) {
            // Define 7 individual digit positions (matching extraction grid)
            const gridTop = 0.15;
            const gridLeft = 0.20;
            const gridWidth = 0.60;
            const gridHeight = 0.15;
            const digitWidth = gridWidth / 7.5;
            
            const results = {};
            
            for (let i = 1; i <= 7; i++) {
                let digitLeft = gridLeft + ((i - 1) * digitWidth);
                
                // Add separator space after 4th digit
                if (i > 4) {
                    digitLeft += digitWidth * 0.5;
                }
                
                const digitBox = {
                    id: `digit-${i}`,
                    top: gridTop,
                    left: digitLeft,
                    width: digitWidth,
                    height: gridHeight,
                    digitIndex: i
                };
                
                const detection = detectNumbersInBox(imageData, canvasWidth, canvasHeight, digitBox);
                results[`digit-${i}`] = {
                    hasNumbers: detection.hasNumbers,
                    quality: detection.quality,
                    digitIndex: i
                };
            }
            
            return results;
        }

        function checkCustomerIdBox(imageData, canvasWidth, canvasHeight) {
            const customerBox = {
                id: 'customer-id',
                top: 0.85,
                left: 0.30,
                width: 0.40,
                height: 0.12
            };
            
            return detectNumbersInBox(imageData, canvasWidth, canvasHeight, customerBox);
        }


        function updateDigitVisualFeedback(digitResults) {
            let allDigitsDetected = true;
            let detectedCount = 0;
            let totalQuality = 0;
            
            // Update each digit box
            for (let i = 1; i <= 7; i++) {
                const digitResult = digitResults[`digit-${i}`];
                const statusElement = document.getElementById(`digit-${i}-status`);
                const parentBox = statusElement?.closest('.flex-1');
                
                if (digitResult.hasNumbers && digitResult.quality > 0.4) {
                    // Good detection
                    if (statusElement) {
                        statusElement.textContent = '✓';
                        statusElement.className = i <= 4 ? 'text-xs font-bold text-green-800' : 'text-xs font-bold text-red-800';
                    }
                    if (parentBox) {
                        if (i <= 4) {
                            parentBox.className = 'flex-1 border-2 border-green-600 bg-green-200 bg-opacity-80 flex items-center justify-center';
                        } else {
                            parentBox.className = 'flex-1 border-2 border-red-600 bg-red-200 bg-opacity-80 flex items-center justify-center';
                        }
                    }
                    detectedCount++;
                    totalQuality += digitResult.quality;
                } else {
                    // Poor or no detection
                    if (statusElement) {
                        statusElement.textContent = i.toString();
                        statusElement.className = i <= 4 ? 'text-xs font-bold text-green-700' : 'text-xs font-bold text-red-700';
                    }
                    if (parentBox) {
                        if (i <= 4) {
                            parentBox.className = 'flex-1 border-2 border-green-500 bg-green-50 bg-opacity-40 flex items-center justify-center';
                        } else {
                            parentBox.className = 'flex-1 border-2 border-red-500 bg-red-50 bg-opacity-40 flex items-center justify-center';
                        }
                    }
                    allDigitsDetected = false;
                }
            }
            
            
            // Store results for auto-capture check
            digitDetectionResults = digitResults;
            
            return { allDigitsDetected, detectedCount, avgQuality: totalQuality / Math.max(detectedCount, 1) };
        }

        function updateCustomerIdVisualFeedback(customerIdResult) {
            const boxElement = document.getElementById('customer-id-box');
            
            if (customerIdResult.hasNumbers && customerIdResult.quality > 0.3) {
                if (boxElement) {
                    boxElement.className = boxElement.className.replace('border-blue-400', 'border-blue-600') + ' bg-blue-200 bg-opacity-60';
                }
            } else {
                if (boxElement) {
                    boxElement.className = 'w-full h-full border-4 border-blue-400 bg-blue-50 bg-opacity-30 rounded-lg flex items-center justify-center';
                }
            }
        }


        function checkAutoCaptureTrigger(digitResults) {
            // Count how many digits have good detection
            let goodDigits = 0;
            let totalQuality = 0;
            
            for (let i = 1; i <= 7; i++) {
                const result = digitResults[`digit-${i}`];
                if (result.hasNumbers && result.quality > 0.5) {
                    goodDigits++;
                    totalQuality += result.quality;
                }
            }
            
            const avgQuality = goodDigits > 0 ? totalQuality / goodDigits : 0;
            
            // Update instruction based on detection
            const instructionElement = document.getElementById('boxes-instruction');
            if (goodDigits >= 7 && avgQuality > 0.6) {
                consecutiveGoodFrames++;
                if (instructionElement) {
                    instructionElement.textContent = `🎯 ALL DIGITS ALIGNED! Auto-capture in ${REQUIRED_GOOD_FRAMES - consecutiveGoodFrames + 1}...`;
                }
                
                // Trigger auto-capture
                if (consecutiveGoodFrames >= REQUIRED_GOOD_FRAMES && autoCaptureEnabled) {
                    triggerAutoCapture(goodDigits, avgQuality);
                }
            } else {
                consecutiveGoodFrames = 0;
                if (instructionElement) {
                    instructionElement.textContent = `🔍 Aligning digits in boxes`;
                }
            }
            
            console.log('Auto-capture check:', {
                goodDigits: goodDigits,
                avgQuality: Math.round(avgQuality * 100) + '%',
                consecutiveGoodFrames: consecutiveGoodFrames,
                autoCaptureEnabled: autoCaptureEnabled
            });
        }

        function triggerAutoCapture(goodDigits, avgQuality) {
            autoCaptureEnabled = false; // Prevent multiple captures
            
            console.log('AUTO-CAPTURE TRIGGERED:', {
                detectedDigits: goodDigits,
                averageQuality: Math.round(avgQuality * 100) + '%',
                timestamp: new Date().toISOString()
            });
            
            // Update instruction
            const instructionElement = document.getElementById('boxes-instruction');
            if (instructionElement) {
                instructionElement.textContent = '📸 AUTO-CAPTURING: All digits detected!';
            }
            
            // Show success toast
            showToast(`🎯 AUTO-CAPTURE: All 7 digits detected (${Math.round(avgQuality * 100)}% quality)!`, 'success', 3000);
            
            // Capture after brief delay
            setTimeout(() => {
                capturePhoto();
            }, 1000);
        }
        
        function detectNumbersInBox(imageData, canvasWidth, canvasHeight, box) {
            const data = imageData.data;
            
            // Calculate actual pixel coordinates from percentages
            const startX = Math.floor(box.left * canvasWidth);
            const startY = Math.floor(box.top * canvasHeight);
            const endX = Math.floor((box.left + box.width) * canvasWidth);
            const endY = Math.floor((box.top + box.height) * canvasHeight);
            
            let contrastPixels = 0;
            let totalPixels = 0;
            let edgePixels = 0;
            let strongEdgePixels = 0;
            let digitalPatternPixels = 0;
            let darkTextPixels = 0;
            let brightBackgroundPixels = 0;
            let structuredPixels = 0;
            
            // Enhanced sampling - very fine for upper box
            const sampleStep = box.id === 'full-reading' ? 1 : 2; // Even higher resolution for upper box
            
            // Sample pixels within the box area
            for (let y = startY; y < endY; y += sampleStep) {
                for (let x = startX; x < endX; x += sampleStep) {
                    if (x < canvasWidth - 2 && y < canvasHeight - 2) {
                        const pixelIndex = (y * canvasWidth + x) * 4;
                        const rightIndex = (y * canvasWidth + (x + 1)) * 4;
                        const bottomIndex = ((y + 1) * canvasWidth + x) * 4;
                        const diagonalIndex = ((y + 1) * canvasWidth + (x + 1)) * 4;
                        
                        // Calculate brightness values
                        const brightness = (data[pixelIndex] + data[pixelIndex + 1] + data[pixelIndex + 2]) / 3;
                        const rightBrightness = (data[rightIndex] + data[rightIndex + 1] + data[rightIndex + 2]) / 3;
                        const bottomBrightness = (data[bottomIndex] + data[bottomIndex + 1] + data[bottomIndex + 2]) / 3;
                        const diagonalBrightness = (data[diagonalIndex] + data[diagonalIndex + 1] + data[diagonalIndex + 2]) / 3;
                        
                        // Calculate edge strength in multiple directions
                        const horizontalEdge = Math.abs(brightness - rightBrightness);
                        const verticalEdge = Math.abs(brightness - bottomBrightness);
                        const diagonalEdge = Math.abs(brightness - diagonalBrightness);
                        const edgeStrength = Math.max(horizontalEdge, verticalEdge, diagonalEdge);
                        
                        // Look for different types of patterns
                        if (edgeStrength > 50) {
                            strongEdgePixels++; // Very sharp transitions
                        }
                        if (edgeStrength > 30) {
                            edgePixels++; // Clear edges
                        }
                        
                        // Detect typical water meter display patterns
                        // Dark numbers on light LCD background
                        if (brightness < 60) {
                            darkTextPixels++; // Dark areas (likely text)
                        }
                        if (brightness > 200) {
                            brightBackgroundPixels++; // Bright background
                        }
                        
                        // Look for structured digital display patterns
                        if ((brightness < 70 && (rightBrightness > 180 || bottomBrightness > 180)) || 
                            (brightness > 190 && (rightBrightness < 80 || bottomBrightness < 80))) {
                            digitalPatternPixels++;
                        }
                        
                        // Look for rectangular/structured patterns (typical of 7-segment displays)
                        const avgNeighbor = (rightBrightness + bottomBrightness + diagonalBrightness) / 3;
                        if (Math.abs(brightness - avgNeighbor) > 60) {
                            structuredPixels++; // High contrast with neighbors
                        }
                        
                        // General contrast areas
                        if (brightness < 80 || brightness > 200) {
                            contrastPixels++;
                        }
                        
                        totalPixels++;
                    }
                }
            }
            
            // Calculate ratios
            const edgeRatio = edgePixels / totalPixels;
            const strongEdgeRatio = strongEdgePixels / totalPixels;
            const contrastRatio = contrastPixels / totalPixels;
            const digitalRatio = digitalPatternPixels / totalPixels;
            const darkTextRatio = darkTextPixels / totalPixels;
            const brightBgRatio = brightBackgroundPixels / totalPixels;
            const structuredRatio = structuredPixels / totalPixels;
            
            // Water meter specific patterns
            const lcdPattern = (darkTextRatio > 0.1 && brightBgRatio > 0.3); // LCD display characteristics
            const goodContrast = (darkTextRatio > 0.08 && brightBgRatio > 0.25);
            
            // Enhanced detection logic for upper box (meter reading)
            let hasNumbers = false;
            let quality = 0;
            
            if (box.id === 'full-reading') {
                // Multi-factor quality calculation optimized for water meters
                const baseQuality = Math.min(1.0, 
                    (strongEdgeRatio * 2.0) +           // Sharp edges are crucial
                    (structuredRatio * 1.5) +           // Structured patterns
                    (digitalRatio * 1.2) +              // Digital display patterns
                    (edgeRatio * 0.8) +                 // General edges
                    (contrastRatio * 0.5)               // Basic contrast
                );
                
                // Bonus for LCD-like patterns
                const lcdBonus = lcdPattern ? 0.3 : (goodContrast ? 0.15 : 0);
                
                quality = Math.min(1.0, baseQuality + lcdBonus);
                
                // More lenient detection criteria
                hasNumbers = (strongEdgeRatio > 0.05 && structuredRatio > 0.1) ||  // Clear structured content
                            (edgeRatio > 0.15 && digitalRatio > 0.05) ||             // Digital patterns
                            (lcdPattern) ||                                           // LCD display detected
                            (structuredRatio > 0.2);                                 // High structure
                
                // DEBUG: Log detailed metrics for troubleshooting
                if (totalPixels > 0) {
                    console.log('UPPER BOX DETECTION METRICS:', {
                        quality: Math.round(quality * 100) + '%',
                        hasNumbers: hasNumbers,
                        metrics: {
                            strongEdgeRatio: Math.round(strongEdgeRatio * 100) + '%',
                            structuredRatio: Math.round(structuredRatio * 100) + '%',
                            digitalRatio: Math.round(digitalRatio * 100) + '%',
                            darkTextRatio: Math.round(darkTextRatio * 100) + '%',
                            brightBgRatio: Math.round(brightBgRatio * 100) + '%',
                            lcdPattern: lcdPattern,
                            goodContrast: goodContrast
                        }
                    });
                }
            } else {
                // Simpler calculation for customer ID box
                quality = (edgeRatio * 0.6) + (contrastRatio * 0.3) + (strongEdgeRatio * 0.1);
                hasNumbers = (edgeRatio > 0.15 && contrastRatio > 0.2) || (edgeRatio > 0.25);
            }
            
            return {
                hasNumbers: hasNumbers,
                quality: quality,
                metrics: {
                    edgeRatio: edgeRatio,
                    strongEdgeRatio: strongEdgeRatio,
                    contrastRatio: contrastRatio,
                    digitalRatio: digitalRatio,
                    darkTextRatio: darkTextRatio,
                    brightBgRatio: brightBgRatio,
                    structuredRatio: structuredRatio,
                    lcdPattern: lcdPattern
                }
            };
        }
        

        // OCR Processing Functions for Box Regions
        async function processOCRBoxes() {
            if (!capturedPhotoBlob || !capturedPhotoBlob.boxes) {
                showToast('No box regions to process', 'error');
                return;
            }

            const ocrResults = document.getElementById('ocr-results');
            const ocrLoading = document.getElementById('ocr-loading');
            const ocrSuggestions = document.getElementById('ocr-suggestions');

            // Show OCR panel and loading
            ocrResults.classList.remove('hidden');
            ocrLoading.classList.remove('hidden');
            ocrSuggestions.innerHTML = '';
            
            try {
                const results = {};
                
                // Process each box region separately
                for (const boxInfo of capturedPhotoBlob.boxes) {
                    console.log(`Processing OCR for ${boxInfo.name}...`);
                    
                    const formData = new FormData();
                    formData.append('image', boxInfo.blob, `${boxInfo.id}.jpg`);
                    formData.append('box_id', boxInfo.id);
                    formData.append('box_name', boxInfo.name);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                    const response = await fetch('/admin/meter/ocr', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        results[boxInfo.id] = {
                            ...result,
                            boxName: boxInfo.name,
                            coordinates: boxInfo.coordinates
                        };
                        console.log(`${boxInfo.name} OCR Result:`, result);
                    } else {
                        console.error(`${boxInfo.name} OCR Failed:`, result.message);
                        results[boxInfo.id] = {
                            success: false,
                            message: result.message,
                            boxName: boxInfo.name
                        };
                    }
                }
                
                ocrLoading.classList.add('hidden');
                displayBoxOCRResults(results);

            } catch (error) {
                console.error('Box OCR Error:', error);
                ocrLoading.classList.add('hidden');
                showToast('Error processing box regions', 'error');
                ocrResults.classList.add('hidden');
            }
        }

        // Legacy function for compatibility
        async function processOCR() {
            // Redirect to box processing if we have box data
            if (capturedPhotoBlob && capturedPhotoBlob.boxes) {
                return await processOCRBoxes();
            }
            
            showToast('No image data to process', 'error');
        }

        function displayBoxOCRResults(results) {
            // Hide traditional OCR panel
            document.getElementById('ocr-results').classList.add('hidden');
            
            console.log('Displaying Individual Digit OCR Results:', results);
            
            // Separate digit results from customer ID
            const digitResults = {};
            let customerIdResult = null;
            
            Object.keys(results).forEach(key => {
                if (key.startsWith('digit-')) {
                    digitResults[key] = results[key];
                } else if (key === 'customer-id') {
                    customerIdResult = results[key];
                }
            });
            
            // Show main OCR values display
            document.getElementById('ocr-values-display').classList.remove('hidden');
            
            // Process individual digit results and reconstruct meter reading
            const reconstructedReading = reconstructMeterReading(digitResults);
            
            if (reconstructedReading.success) {
                document.getElementById('display-full-reading').textContent = reconstructedReading.fullValue;
                document.getElementById('display-normal-value').textContent = reconstructedReading.normalValue;
                document.getElementById('display-precise-value').textContent = reconstructedReading.preciseValue;
                document.getElementById('display-method').textContent = 'digit-grid';
                
                const avgConfidence = reconstructedReading.averageConfidence;
                const confidenceElement = document.getElementById('display-confidence');
                confidenceElement.textContent = Math.round(avgConfidence) + '%';
                
                // Color code confidence
                if (avgConfidence >= 80) {
                    confidenceElement.className = 'font-mono font-bold text-green-600 ml-1';
                } else if (avgConfidence >= 60) {
                    confidenceElement.className = 'font-mono font-bold text-yellow-600 ml-1';
                } else {
                    confidenceElement.className = 'font-mono font-bold text-red-600 ml-1';
                }
                
                console.log('DIGIT GRID SUCCESS:', {
                    fullValue: reconstructedReading.fullValue,
                    digitResults: reconstructedReading.digitResults,
                    averageConfidence: avgConfidence,
                    successfulDigits: reconstructedReading.successfulDigits
                });
            } else {
                document.getElementById('display-full-reading').textContent = 'PARTIAL';
                document.getElementById('display-normal-value').textContent = reconstructedReading.normalValue || '-';
                document.getElementById('display-precise-value').textContent = reconstructedReading.preciseValue || '-';
                document.getElementById('display-method').textContent = 'digit-grid';
                document.getElementById('display-confidence').textContent = Math.round(reconstructedReading.averageConfidence) + '%';
                document.getElementById('display-confidence').className = 'font-mono font-bold text-yellow-600 ml-1';
                
                console.log('DIGIT GRID PARTIAL:', reconstructedReading);
            }
            
            // Process customer ID result - handle both single digit and full OCR formats
            if (customerIdResult && customerIdResult.success) {
                let customerIdText = '-';
                
                // Check for single digit format first
                if (customerIdResult.extractedText) {
                    customerIdText = customerIdResult.extractedText;
                } else if (customerIdResult.structured_data) {
                    // Full OCR format
                    const structured = customerIdResult.structured_data;
                    customerIdText = structured.customer_id || structured.full_value || '-';
                }
                
                document.getElementById('display-customer-id').textContent = customerIdText;
                
                console.log('CUSTOMER ID SUCCESS:', {
                    customerId: customerIdText,
                    confidence: customerIdResult.confidence,
                    method: customerIdResult.method
                });
            } else {
                document.getElementById('display-customer-id').textContent = 'FAILED';
                console.log('CUSTOMER ID FAILED:', customerIdResult);
            }
            
            // Summary feedback
            const digitSuccess = reconstructedReading.success;
            const customerSuccess = customerIdResult?.success;
            
            if (digitSuccess && customerSuccess) {
                showToast(`🎯 SUCCESS: All digits + customer ID recognized! (${reconstructedReading.successfulDigits}/7 digits)`, 'success', 4000);
            } else if (digitSuccess) {
                showToast(`⚠️ PARTIAL: Digits recognized (${reconstructedReading.successfulDigits}/7), customer ID failed`, 'success', 4000);
            } else if (customerSuccess) {
                showToast('⚠️ PARTIAL: Customer ID success, digit recognition incomplete', 'success', 4000);
            } else {
                showToast(`❌ INCOMPLETE: Only ${reconstructedReading.successfulDigits}/7 digits recognized`, 'error', 4000);
            }
            
            // Update visual grid status
            updateDigitGridStatus(digitResults);
        }

        function reconstructMeterReading(digitResults) {
            const reconstruction = {
                digitResults: {},
                fullValue: '',
                normalValue: '',
                preciseValue: '',
                successfulDigits: 0,
                totalConfidence: 0,
                averageConfidence: 0,
                success: false
            };
            
            console.log('Reconstructing meter reading from:', digitResults);
            
            // Process each digit (1-7)
            for (let i = 1; i <= 7; i++) {
                const digitKey = `digit-${i}`;
                const digitResult = digitResults[digitKey];
                
                console.log(`Processing ${digitKey}:`, digitResult);
                
                if (digitResult && digitResult.success && digitResult.extractedText) {
                    const digit = digitResult.extractedText;
                    const confidence = digitResult.confidence || 0;
                    
                    reconstruction.digitResults[i] = {
                        digit: digit,
                        confidence: confidence
                    };
                    reconstruction.fullValue += digit;
                    reconstruction.successfulDigits++;
                    reconstruction.totalConfidence += confidence;
                    
                    console.log(`${digitKey} SUCCESS: ${digit} (${confidence}%)`);
                } else {
                    reconstruction.digitResults[i] = {
                        digit: '?',
                        confidence: 0
                    };
                    reconstruction.fullValue += '?';
                    
                    console.log(`${digitKey} FAILED:`, digitResult?.message || 'No result');
                }
            }
            
            // Calculate average confidence
            reconstruction.averageConfidence = reconstruction.successfulDigits > 0 
                ? reconstruction.totalConfidence / reconstruction.successfulDigits 
                : 0;
            
            // Split into normal (1-4) and precise (5-7) values
            reconstruction.normalValue = reconstruction.fullValue.substring(0, 4);
            reconstruction.preciseValue = reconstruction.fullValue.substring(4, 7);
            
            // Consider success if we got at least 4 out of 7 digits (lowered threshold for testing)
            reconstruction.success = reconstruction.successfulDigits >= 4;
            
            console.log('Reconstruction complete:', {
                fullValue: reconstruction.fullValue,
                successfulDigits: reconstruction.successfulDigits,
                averageConfidence: reconstruction.averageConfidence,
                success: reconstruction.success
            });
            
            return reconstruction;
        }

        function updateDigitGridStatus(digitResults) {
            console.log('Updating digit grid status with:', digitResults);
            
            // Update individual digit status in the visual grid
            for (let i = 1; i <= 7; i++) {
                const statusElement = document.getElementById(`digit-${i}-status`);
                const digitResult = digitResults[`digit-${i}`];
                
                console.log(`Updating digit ${i}:`, digitResult);
                
                if (statusElement) {
                    if (digitResult && digitResult.success && digitResult.extractedText) {
                        statusElement.textContent = digitResult.extractedText;
                        statusElement.className = 'text-xs font-bold text-green-800';
                        
                        // Update the parent box border to indicate success
                        const parentBox = statusElement.closest('.flex-1');
                        if (parentBox) {
                            if (i <= 4) {
                                parentBox.className = 'flex-1 border-2 border-green-600 bg-green-100 bg-opacity-60 flex items-center justify-center';
                            } else {
                                parentBox.className = 'flex-1 border-2 border-red-600 bg-red-100 bg-opacity-60 flex items-center justify-center';
                            }
                        }
                    } else {
                        statusElement.textContent = '?';
                        statusElement.className = 'text-xs font-bold text-red-700';
                        
                        // Update the parent box border to indicate failure
                        const parentBox = statusElement.closest('.flex-1');
                        if (parentBox) {
                            if (i <= 4) {
                                parentBox.className = 'flex-1 border-2 border-gray-400 bg-gray-100 bg-opacity-40 flex items-center justify-center';
                            } else {
                                parentBox.className = 'flex-1 border-2 border-gray-400 bg-gray-100 bg-opacity-40 flex items-center justify-center';
                            }
                        }
                    }
                }
            }
            
        }

        // Legacy function for compatibility
        function displayOCRResults(result) {
            // Convert single result to box format for compatibility
            const boxResults = {
                'upper-box': result
            };
            displayBoxOCRResults(boxResults);
        }

        function clearOCRDisplay() {
            // Reset all OCR display values
            document.getElementById('display-full-reading').textContent = '-';
            document.getElementById('display-customer-id').textContent = '-';
            document.getElementById('display-normal-value').textContent = '-';
            document.getElementById('display-precise-value').textContent = '-';
            document.getElementById('display-method').textContent = '-';
            document.getElementById('display-confidence').textContent = '-';
            document.getElementById('display-confidence').className = 'font-mono font-bold text-green-600 ml-1';
        }




        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            stopCamera();
        });
    </script>
</body>
</html>