/**
 * Camera Capture Widget
 * Kids Store v5 — Admin Panel
 *
 * Supports both product images and variant images.
 * Captured images are injected into the hidden file input via DataTransfer,
 * so the existing PHP upload handler requires NO changes.
 */

(function () {
  "use strict";

  /* ── State ─────────────────────────────────────────────── */
  let stream        = null;   // MediaStream
  let facingMode    = "environment"; // rear camera default
  let capturedBlobs = [];     // Blob[] of captured frames
  let previewUrls   = [];     // ObjectURL[] for thumbnail display
  let targetInput   = null;   // the hidden <input type="file"> to inject into
  let targetStrip   = null;   // the .capture-preview-strip element to update
  let maxAllowed    = 10;     // overridden per widget based on existing image count

  /* ── DOM refs (created once, reused) ────────────────────── */
  let modal, video, canvas, ctx,
      thumbStrip, shutterBtn, doneBtn,
      countBadge, maxWarn, errorMsg, flashEl;

  /* ── Build modal DOM once ────────────────────────────────── */
  function buildModal() {
    if (document.getElementById("cameraModal")) return;

    const m = document.createElement("div");
    m.id = "cameraModal";
    m.innerHTML = `
      <div class="cam-topbar">
        <button type="button" class="cam-close-btn" id="camCloseBtn">✕ Close</button>
        <span class="cam-topbar-title">📷 Take Photos</span>
        <button type="button" class="cam-flip-btn" id="camFlipBtn">🔄 Flip</button>
      </div>

      <div class="cam-viewfinder-wrap">
        <video id="camVideo" autoplay playsinline muted></video>
        <canvas id="camCanvas"></canvas>

        <div class="cam-count-badge" id="camCountBadge">0 photos</div>

        <!-- Flash overlay -->
        <div id="camFlashEl" style="position:absolute;inset:0;background:white;pointer-events:none;"></div>

        <!-- Error / permission denied -->
        <div class="cam-error-msg" id="camErrorMsg">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <strong id="camErrorTitle">Camera unavailable</strong>
          <span id="camErrorDesc">Please allow camera access or use the gallery picker.</span>
        </div>
      </div>

      <div class="cam-controls">
        <div class="cam-thumb-strip" id="camThumbStrip"></div>
        <p class="cam-max-warning" id="camMaxWarn">Maximum photos reached</p>
        <div class="cam-shutter-row">
          <button type="button" class="cam-shutter" id="camShutter" title="Take photo"></button>
          <button type="button" class="cam-done-btn" id="camDoneBtn" disabled>Done (0)</button>
        </div>
      </div>
    `;
    document.body.appendChild(m);

    /* Cache refs */
    modal      = m;
    video      = m.querySelector("#camVideo");
    canvas     = m.querySelector("#camCanvas");
    ctx        = canvas.getContext("2d");
    thumbStrip = m.querySelector("#camThumbStrip");
    shutterBtn = m.querySelector("#camShutter");
    doneBtn    = m.querySelector("#camDoneBtn");
    countBadge = m.querySelector("#camCountBadge");
    maxWarn    = m.querySelector("#camMaxWarn");
    errorMsg   = m.querySelector("#camErrorMsg");
    flashEl    = m.querySelector("#camFlashEl");

    /* Events */
    m.querySelector("#camCloseBtn").addEventListener("click", closeModal);
    m.querySelector("#camFlipBtn").addEventListener("click", flipCamera);
    shutterBtn.addEventListener("click", captureFrame);
    doneBtn.addEventListener("click", confirmCaptures);
  }

  /* ── Open modal for a given widget ─────────────────────── */
  function openModal(hiddenInput, previewStrip, existingCount) {
    buildModal();
    targetInput   = hiddenInput;
    targetStrip   = previewStrip;
    maxAllowed    = Math.max(0, (window.MAX_IMAGES_PER_PRODUCT || 10) - existingCount);

    /* Reset state */
    capturedBlobs = [];
    previewUrls.forEach(u => URL.revokeObjectURL(u));
    previewUrls   = [];
    thumbStrip.innerHTML = "";
    updateUI();
    errorMsg.classList.remove("visible");

    modal.classList.add("active");
    document.body.style.overflow = "hidden";

    startCamera();
  }

  /* ── Camera stream ──────────────────────────────────────── */
  async function startCamera() {
    stopStream();
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode, width: { ideal: 1920 }, height: { ideal: 1080 } },
        audio: false
      });
      video.srcObject = stream;
      errorMsg.classList.remove("visible");
      shutterBtn.disabled = false;
    } catch (err) {
      showError(err);
    }
  }

  function showError(err) {
    shutterBtn.disabled = true;
    errorMsg.classList.add("visible");
    const titleEl = document.getElementById("camErrorTitle");
    const descEl  = document.getElementById("camErrorDesc");
    if (err && err.name === "NotAllowedError") {
      titleEl.textContent = "Camera access denied";
      descEl.textContent  = "Allow camera in your browser settings, or use the 'Gallery' button instead.";
    } else if (err && err.name === "NotFoundError") {
      titleEl.textContent = "No camera found";
      descEl.textContent  = "This device has no camera. Use the 'Gallery' button instead.";
    } else {
      titleEl.textContent = "Camera unavailable";
      descEl.textContent  = err ? err.message : "Unknown error.";
    }
  }

  function stopStream() {
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
    video.srcObject = null;
  }

  async function flipCamera() {
    facingMode = facingMode === "environment" ? "user" : "environment";
    await startCamera();
  }

  /* ── Capture a frame ────────────────────────────────────── */
  function captureFrame() {
    if (!stream || capturedBlobs.length >= maxAllowed) return;

    // Fix #3: Flash uses CSS animation class — no inline style juggling
    flashEl.classList.remove("cam-flash");
    void flashEl.offsetWidth; // force reflow so animation restarts
    flashEl.classList.add("cam-flash");
    flashEl.addEventListener("animationend", () => {
      flashEl.classList.remove("cam-flash");
    }, { once: true });

    // Draw video frame to canvas
    canvas.width  = video.videoWidth  || 1280;
    canvas.height = video.videoHeight || 720;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(blob => {
      if (!blob) return;
      capturedBlobs.push(blob);
      const url = URL.createObjectURL(blob);
      previewUrls.push(url);
      addThumbToStrip(url, capturedBlobs.length - 1);
      updateUI();
    }, "image/jpeg", 0.85);
  }

  /* ── Thumbnail in modal strip ───────────────────────────── */
  function addThumbToStrip(url, index) {
    const div = document.createElement("div");
    div.className = "cam-thumb";
    div.dataset.index = index;
    div.innerHTML = `
      <img src="${url}" alt="capture ${index + 1}">
      <button type="button" class="cam-thumb-remove" title="Remove">✕</button>
    `;
    div.querySelector(".cam-thumb-remove").addEventListener("click", () => removeCapture(index));
    thumbStrip.appendChild(div);
    // scroll to end
    thumbStrip.scrollLeft = thumbStrip.scrollWidth;
  }

  function removeCapture(index) {
    // Revoke old URL
    URL.revokeObjectURL(previewUrls[index]);
    capturedBlobs.splice(index, 1);
    previewUrls.splice(index, 1);

    // Rebuild strip
    thumbStrip.innerHTML = "";
    previewUrls.forEach((url, i) => addThumbToStrip(url, i));
    updateUI();
  }

  function updateUI() {
    const count = capturedBlobs.length;
    countBadge.textContent = count === 1 ? "1 photo" : count + " photos";
    doneBtn.textContent    = count > 0 ? `Done (${count})` : "Done";
    doneBtn.disabled       = count === 0;
    shutterBtn.disabled    = count >= maxAllowed;
    maxWarn.classList.toggle("visible", count >= maxAllowed && maxAllowed > 0);
  }

  /* ── Confirm and inject into form ─────────────────────── */
  function confirmCaptures() {
    if (capturedBlobs.length === 0) return;

    // Inject blobs into the hidden file input
    if (typeof DataTransfer !== "undefined") {
      const dt = new DataTransfer();
      capturedBlobs.forEach((blob, i) => {
        // Fix #2: Skip null placeholders pushed by openGallery()
        if (!blob) return;
        dt.items.add(new File([blob], `capture_${Date.now()}_${i}.jpg`, { type: "image/jpeg" }));
      });
      targetInput.files = dt.files;
    } else {
      // Fallback: alert and let user use gallery
      alert("Your browser doesn't support direct camera upload. Please use the gallery button.");
      closeModal();
      return;
    }

    // Update the preview strip on the form
    updateFormPreviewStrip();

    closeModal();
  }

  /* ── Update the form's inline preview strip ─────────────── */
  function updateFormPreviewStrip() {
    if (!targetStrip) return;
    targetStrip.innerHTML = "";
    previewUrls.forEach((url, i) => {
      const div = document.createElement("div");
      div.className = "strip-thumb";
      div.innerHTML = `
        <img src="${url}" alt="preview ${i + 1}">
        <button type="button" class="strip-remove" title="Remove" data-index="${i}">✕</button>
      `;
      div.querySelector(".strip-remove").addEventListener("click", () => {
        removeFromInput(i);
      });
      targetStrip.appendChild(div);
    });
  }

  /* Remove one image from the form input */
  function removeFromInput(index) {
    // Rebuild DataTransfer without this index
    if (typeof DataTransfer === "undefined" || !targetInput) return;
    const dt = new DataTransfer();
    const files = Array.from(targetInput.files);
    files.forEach((f, i) => {
      if (i !== index) dt.items.add(f);
    });
    targetInput.files = dt.files;

    // Also remove from previewUrls
    URL.revokeObjectURL(previewUrls[index]);
    previewUrls.splice(index, 1);
    capturedBlobs.splice(index, 1);
    updateFormPreviewStrip();
  }

  /* ── Close modal ────────────────────────────────────────── */
  function closeModal() {
    stopStream();
    modal.classList.remove("active");
    document.body.style.overflow = "";
  }

  /* ── Gallery picker trigger ─────────────────────────────── */
  function openGallery(hiddenInput, previewStrip) {
    targetInput  = hiddenInput;
    targetStrip  = previewStrip;

    // Re-enable gallery on the hidden input temporarily
    hiddenInput.removeAttribute("capture");
    hiddenInput.click();

    // After selection, update the preview strip
    hiddenInput.addEventListener("change", function onGalleryChange() {
      hiddenInput.removeEventListener("change", onGalleryChange);
      if (!hiddenInput.files || hiddenInput.files.length === 0) return;

      // Revoke old preview URLs first
      previewUrls.forEach(u => URL.revokeObjectURL(u));
      previewUrls   = [];
      capturedBlobs = [];

      Array.from(hiddenInput.files).forEach(file => {
        const url = URL.createObjectURL(file);
        previewUrls.push(url);
        capturedBlobs.push(null); // placeholder (file already in input, no blob needed)
      });
      updateFormPreviewStrip();
    }, { once: true });
  }

  /* ── Public API ─────────────────────────────────────────── */
  window.CameraCapture = {
    open: openModal,
    openGallery: openGallery
  };

})();
