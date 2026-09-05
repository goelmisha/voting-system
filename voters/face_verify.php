<?php
session_start();
require_once __DIR__ . '/../db.php';

// Authentication Check: Ensure voter is logged in
if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];

// Fetch voter registration details
try {
    $stmt = $pdo->prepare("SELECT fullname, photo, status, has_voted FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$vid]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voter) {
        header("Location: ../logout.php");
        exit();
    }

    // Ensure voter is approved by admin
    $status = strtolower(trim($voter['status'] ?? 'pending'));
    if ($status !== 'approved') {
        header("Location: ../login.php");
        exit();
    }

    // If voter already voted, redirect back to dashboard
    $has_voted = ((int)($voter['has_voted'] ?? 0) === 1);
    if ($has_voted) {
        header("Location: dashboard.php");
        exit();
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Locate voter profile image
$imageName = trim($voter['photo'] ?? '');
$photoPath = "../images/default.png";

if (!empty($imageName) && file_exists(__DIR__ . "/../images/" . $imageName)) {
    $photoPath = "../images/" . $imageName;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Face Verification - Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <!-- Load Face-API.js library -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <style>
        :root {
            --primary-color: blueviolet;
            --primary-hover: #701eb8;
            --bg-light: #f8f9fa;
        }

        body {
            background-color: var(--bg-light);
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .header {
            background-color: var(--primary-color);
            color: #ffffff;
            height: 9vh;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .verify-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin: 30px auto;
        }

        .ref-photo {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            padding: 2px;
            display: block;
            margin: 0 auto 10px auto;
        }

        .video-container {
            position: relative;
            display: inline-block;
            margin: 15px auto;
        }

        #webcam {
            width: 100%;
            max-width: 420px;
            height: auto;
            border-radius: 8px;
            border: 2px solid #333333;
            transform: scaleX(-1); /* Mirror view */
            background-color: #000000;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: bold;
            padding: 10px 24px;
            border-radius: 6px;
            border: none;
        }

        .btn-custom:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
        }

        footer {
            text-align: center;
            padding: 15px 0;
            color: #777;
            font-size: 14px;
            background-color: #fff;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>

    <header class="header">
        <h4 class="m-0 font-weight-bold">Online Voting System — Biometric Verification</h4>
    </header>

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="verify-card text-center">
                    <h4 class="font-weight-bold mb-2">Voter Face Verification</h4>
                    <p class="text-muted small mb-3">Position your face in front of the camera. The system will match your live face with your registered identification photo.</p>

                    <!-- Registered ID Profile Reference -->
                    <div class="mb-3">
                        <img id="refImg" src="<?= htmlspecialchars($photoPath); ?>" alt="Registered Voter Photo" class="ref-photo" crossorigin="anonymous">
                        <p class="small text-muted mb-0"><strong><?= htmlspecialchars($voter['fullname'] ?? 'Voter'); ?></strong> (Registered Photo)</p>
                    </div>

                    <!-- Live Webcam Feed -->
                    <div class="video-container">
                        <video id="webcam" autoplay muted playsinline></video>
                    </div>

                    <!-- Dynamic Status Display -->
                    <div class="my-3">
                        <div id="statusAlert" class="alert alert-info py-2 d-inline-block px-4 mb-0">
                            Initializing AI facial recognition models...
                        </div>
                    </div>

                    <!-- Action Controls -->
                    <div class="mt-3">
                        <button id="verifyBtn" class="btn btn-success font-weight-bold px-4 py-2" disabled>Scan & Verify Identity</button>
                        <a href="dashboard.php" class="btn btn-outline-secondary px-3 py-2 ml-2">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
    </footer>

    <script>
        const video = document.getElementById('webcam');
        const refImg = document.getElementById('refImg');
        const statusAlert = document.getElementById('statusAlert');
        const verifyBtn = document.getElementById('verifyBtn');

        let registeredMatcher = null;

        async function init() {
            try {
                statusAlert.className = "alert alert-info py-2";
                statusAlert.innerText = "Loading facial detection models...";

                // Load models from the local models folder
                const MODEL_URL = '../models';

                await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

                statusAlert.innerText = "Analyzing registered photo...";

                // Ensure reference image is completely decoded before detection
                if (!refImg.complete || refImg.naturalWidth === 0) {
                    await new Promise((resolve) => {
                        refImg.onload = resolve;
                        refImg.onerror = resolve;
                    });
                }

                // Extract facial descriptors from registered ID photo
                const refDetection = await faceapi.detectSingleFace(refImg)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (!refDetection) {
                    statusAlert.className = "alert alert-warning py-2";
                    statusAlert.innerText = "Unable to detect face in registered profile picture. Please update photo.";
                    return;
                }

                // Matcher threshold (distance <= 0.55 indicates high confidence match)
                registeredMatcher = new faceapi.FaceMatcher(refDetection.descriptor, 0.55);

                // Initialize camera
                statusAlert.innerText = "Accessing live webcam feed...";
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { width: 420, height: 320 } 
                });
                video.srcObject = stream;

                statusAlert.className = "alert alert-primary py-2";
                statusAlert.innerText = "Webcam ready. Center your face and click Scan & Verify.";
                verifyBtn.disabled = false;

            } catch (err) {
                statusAlert.className = "alert alert-danger py-2";
                statusAlert.innerText = "Error: " + err.message;
            }
        }

        // Handle verification click
        verifyBtn.addEventListener('click', async () => {
            verifyBtn.disabled = true;
            statusAlert.className = "alert alert-info py-2";
            statusAlert.innerText = "Scanning and comparing face descriptors...";

            try {
                const liveDetection = await faceapi.detectSingleFace(video)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (!liveDetection) {
                    statusAlert.className = "alert alert-warning py-2";
                    statusAlert.innerText = "No face detected in camera stream. Please look directly into the lens.";
                    verifyBtn.disabled = false;
                    return;
                }

                const bestMatch = registeredMatcher.findBestMatch(liveDetection.descriptor);

                // Euclidean distance check
                if (bestMatch.distance <= 0.55) {
                    statusAlert.className = "alert alert-success py-2 font-weight-bold";
                    statusAlert.innerText = "Identity Verified! Authorizing ballot...";

                    // Send server authorization
                    const response = await fetch('verify_session.php', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ verified: true })
                    });

                    const rawResponse = await response.text();
                    let resData;

                    try {
                        resData = JSON.parse(rawResponse);
                    } catch (parseErr) {
                        throw new Error("Invalid response received from verify_session.php. Make sure the file exists and has no PHP errors.");
                    }

                    if (resData.success) {
                        statusAlert.innerText = "Access granted! Redirecting to ballot...";
                        setTimeout(() => {
                            window.location.href = "dashboard.php";
                        }, 800);
                    } else {
                        statusAlert.className = "alert alert-danger py-2";
                        statusAlert.innerText = "Session error: " + (resData.message || "Failed to set server verification.");
                        verifyBtn.disabled = false;
                    }
                } else {
                    statusAlert.className = "alert alert-danger py-2";
                    statusAlert.innerText = "Face mismatch (Distance: " + bestMatch.distance.toFixed(2) + "). Verification failed.";
                    verifyBtn.disabled = false;
                }
            } catch (scanErr) {
                statusAlert.className = "alert alert-danger py-2";
                statusAlert.innerText = "Scan failed: " + scanErr.message;
                verifyBtn.disabled = false;
            }
        });

        window.addEventListener('load', init);
    </script>

</body>
</html>