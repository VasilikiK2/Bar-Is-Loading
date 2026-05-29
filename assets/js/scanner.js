/**
 * Scanner page JavaScript
 * Στέλνει το barcode στο API checkin.php και εμφανίζει αποτέλεσμα
 */

document.addEventListener("DOMContentLoaded", () => {
  let audioCtx = null;

  // Δημιούργησε το AudioContext μόλις ο χρήστης αγγίξει τη σελίδα
  document.addEventListener(
    "keydown",
    () => {
      if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (audioCtx.state === "suspended") {
        audioCtx.resume();
      }
    },
    { once: false },
  );
  const form = document.getElementById("scan-form");
  const input = document.getElementById("barcode-input");
  const result = document.getElementById("result-area");
  const occupancyEl = document.getElementById("current-occupancy");

  if (!form) return;

  // Auto-focus σε κάθε κενό click
  document.addEventListener("click", (e) => {
    if (!e.target.matches("input, button, a, select, textarea")) {
      input.focus();
    }
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    // Δημιούργησε/ξύπνα το AudioContext εδώ
    if (!audioCtx) {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (audioCtx.state === "suspended") {
      await audioCtx.resume();
    }

    const barcode = input.value.trim();
    if (!barcode) return;
    result.innerHTML =
      '<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>';

    try {
      const fd = new FormData();
      fd.append("barcode", barcode);
      const res = await fetch("api/checkin.php", { method: "POST", body: fd });
      const data = await res.json();
      showResult(data);

      // Ενημέρωσε τον μετρητή παρουσίας
      if (data.occupancy !== undefined && occupancyEl) {
        occupancyEl.textContent = data.occupancy;
      }
    } catch (err) {
      result.innerHTML = `<div class="scan-result scan-error"><div class="icon">⚠️</div>
                <h2>Σφάλμα σύνδεσης</h2><p>${err.message}</p></div>`;
    } finally {
      input.value = "";
      input.focus();
      setTimeout(() => {
        if (result.innerHTML !== "") location.reload();
      }, 8000);
    }
  });

  function showResult(data) {
    let cls = "scan-error",
      icon = "❌",
      title = "";

    if (data.success) {
      if (data.action === "checkin") {
        cls = "scan-success";
        icon = "🟢";
        title = "CHECK-IN";
      } else if (data.action === "checkout") {
        cls = "scan-info";
        icon = "👋";
        title = "CHECK-OUT";
      } else if (data.duplicate) {
        cls = "scan-warning";
        icon = "⚠️";
        title = "";
      }
    }

    let html = `<div class="scan-result ${cls}">`;
    if (title) html += `<div class="action-tag">${title}</div>`;
    html += `<div class="icon">${icon}</div>`;

    if (data.display) {
      html += `<h2>${escapeHtml(data.display.name)}</h2>`;
    }
    html += `<p style="font-size:18px;margin:10px 0">${escapeHtml(data.message)}</p>`;

    if (data.action === "checkout" && data.duration_minutes) {
      const h = Math.floor(data.duration_minutes / 60);
      const m = data.duration_minutes % 60;
      const dur = h > 0 ? `${h}ώ ${m}'` : `${m} λεπτά`;
      html += `<div style="font-size:18px;margin-top:8px">⏱ Διάρκεια προπόνησης: <strong>${dur}</strong></div>`;
    }

    if (data.membership_info && data.action !== "checkout") {
      html += `<hr style="opacity:.3"><div style="font-size:15px">
                <strong>${escapeHtml(data.membership_info.type)}</strong><br>
                ${escapeHtml(data.membership_info.detail)}
            </div>`;
      if (
        data.membership_info.remaining !== undefined &&
        data.membership_info.remaining <= 3 &&
        data.membership_info.remaining > 0
      ) {
        html +=
          '<div class="mt-2"><span class="badge bg-danger">Λίγες προπονήσεις απομένουν!</span></div>';
      }
    }
    html += "</div>";
    result.innerHTML = html;

    playSound(data);
  }

  function escapeHtml(s) {
    return String(s).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  }
  function playSound(data) {
    console.log("playSound called", data.success, data.action);
    console.log("audioCtx:", audioCtx);

    if (!audioCtx) {
      console.log("audioCtx is null!");
      return;
    }
    console.log("audioCtx state:", audioCtx.state);

    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);

    if (!data.success) {
      osc.frequency.setValueAtTime(200, audioCtx.currentTime);
      osc.frequency.setValueAtTime(150, audioCtx.currentTime + 0.2);
      gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);
      osc.start(audioCtx.currentTime);
      osc.stop(audioCtx.currentTime + 0.5);
    } else if (data.action === "checkout") {
      osc.frequency.setValueAtTime(600, audioCtx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(
        400,
        audioCtx.currentTime + 0.3,
      );
      gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
      osc.start(audioCtx.currentTime);
      osc.stop(audioCtx.currentTime + 0.4);
    } else if (data.action === "checkin") {
      osc.frequency.setValueAtTime(800, audioCtx.currentTime);
      osc.frequency.setValueAtTime(1000, audioCtx.currentTime + 0.15);
      gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(
        0.001,
        audioCtx.currentTime + 0.35,
      );
      osc.start(audioCtx.currentTime);
      osc.stop(audioCtx.currentTime + 0.35);
    }
  }
});
