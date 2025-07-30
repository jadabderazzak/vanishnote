
  // Retrieve countdown minutes and redirect URL from data attributes
  //let remainingSeconds = parseInt(noteData.dataset.minutes, 10) * 60;
  /**
 * This script handles the countdown and "burning" animation of a note.
 * Once the countdown reaches zero, it triggers an AJAX call to burn the note on the backend,
 * then performs a shatter animation by fragmenting the note's image into tiles,
 * animating them falling and rotating on a canvas, and finally redirects the user.
 */

document.addEventListener('DOMContentLoaded', () => {
      const noteData = document.getElementById('note-data');      // Hidden element holding data attributes
      if (!noteData) {
        return;
      }
  // === DOM ELEMENTS SELECTION ===
  const note = document.querySelector('.note-content');       // The visible note element
  const canvas = document.getElementById('shatter-canvas');   // Canvas for shatter animation
  const countdownEl = document.getElementById('countdown');   // Countdown timer display
  const noteContainer = document.getElementById('note-container'); // Container wrapping the note (if needed for CSS effects)


  // === SAFETY CHECK ===
  if (!note || !canvas || !countdownEl || !noteContainer || !noteData) {
    console.warn('Required elements are missing.');
    return; // Stop script if essential elements are not found
  }

  // === INITIALIZATION ===
  const ctx = canvas.getContext('2d'); // 2D drawing context for the canvas

  // Countdown in seconds; if data-minutes is set, convert to seconds; otherwise default to 10 seconds
let remainingSeconds = parseInt(noteData.dataset.minutes, 10) * 60;
if (isNaN(remainingSeconds) || remainingSeconds <= 0) remainingSeconds = 60;



  // URL to redirect to after burning and animation
  const redirectUrl = noteData.dataset.redirectUrl;

  // Note slug used for the AJAX request to burn the note on the server
  const noteSlug = noteData.dataset.slug;

  /**
   * Resize canvas to always fill the entire window
   */
  function resizeCanvas() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  }
  resizeCanvas();
  window.addEventListener('resize', resizeCanvas);

  /**
   * Creates tile objects representing fragments of the note for the shatter effect.
   * Each tile contains information about its position, velocity, rotation, lifespan, and opacity.
   *
   * @param {HTMLCanvasElement} img - Canvas image capturing the note.
   * @param {number} [tileSize=40] - Size in pixels of each square tile.
   * @returns {Array<Object>} Array of tile objects.
   */
  function createTiles(img, tileSize = 40) {
    const tiles = [];
    const cols = Math.ceil(img.width / tileSize);
    const rows = Math.ceil(img.height / tileSize);
    const rect = note.getBoundingClientRect(); // Note's position in viewport

    for (let y = 0; y < rows; y++) {
      for (let x = 0; x < cols; x++) {
        const sx = x * tileSize;
        const sy = y * tileSize;
        const sw = Math.min(tileSize, img.width - sx);
        const sh = Math.min(tileSize, img.height - sy);

        // Create tile object with randomized velocities and rotations for natural effect
        tiles.push({
          sx, sy, sw, sh,
          x: rect.left + sx,
          y: rect.top + sy,
          vx: (Math.random() - 0.5) * 8,
          vy: (Math.random() - 0.7) * 8,
          rotation: 0,
          vRotation: (Math.random() - 0.5) * 0.2,
          life: 150 + Math.random() * 50,
          opacity: 1,
        });
      }
    }
    return tiles;
  }

  /**
   * Animate each tile by updating its position, rotation, opacity,
   * and drawing it on the canvas. Gravity is applied to vertical velocity.
   *
   * @param {Array<Object>} tiles - Array of tile objects.
   * @param {HTMLCanvasElement} img - Original canvas image for drawing tiles.
   * @returns {Array<Object>} Array of tiles still visible (opacity > 0).
   */
  function animateTiles(tiles, img) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    tiles.forEach(tile => {
      if (tile.life <= 0) {
        tile.opacity = 0;
        return;
      }

      // Update position with velocity
      tile.x += tile.vx;
      tile.y += tile.vy;

      // Apply gravity
      tile.vy += 0.3;

      // Update rotation
      tile.rotation += tile.vRotation;

      // Decrease life and opacity over time
      tile.life--;
      tile.opacity = tile.life / 150;

      // Draw the tile
      ctx.save();
      ctx.globalAlpha = tile.opacity;

      // Translate to tile center for rotation
      ctx.translate(tile.x + tile.sw / 2, tile.y + tile.sh / 2);
      ctx.rotate(tile.rotation);

      // Draw the portion of the original note image for this tile
      ctx.drawImage(
        img,
        tile.sx, tile.sy, tile.sw, tile.sh,
        -tile.sw / 2, -tile.sh / 2, tile.sw, tile.sh
      );

      ctx.restore();
    });

    // Return tiles that are still visible
    return tiles.filter(t => t.opacity > 0);
  }

  /**
   * Trigger the shatter effect:
   * Captures the note with html2canvas, hides original note,
   * animates the tile fragments until fully faded,
   * then redirects the user.
   */
  function shatterEffect() {
    html2canvas(note).then(canvasImage => {
      note.style.visibility = 'hidden'; // Hide original note

      const tiles = createTiles(canvasImage, 40);

      function animationLoop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const aliveTiles = animateTiles(tiles, canvasImage);

        if (aliveTiles.length > 0) {
          requestAnimationFrame(animationLoop);
        } else {
          // After animation completes, redirect to the specified URL
          window.location.href = redirectUrl;
        }
      }

      animationLoop();
    });
  }

  /**
   * Format seconds into "mm:ss" string format for the countdown display.
   *
   * @param {number} seconds - Time in seconds.
   * @returns {string} Formatted time string.
   */
  function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
  }

  /**
   * Send a POST request to the backend to burn the note by its slug.
   * On success, triggers the shatter animation and redirects.
   *
   * @param {string} slug - The unique slug identifier for the note.
   */
  function burnNote(slug) {
    fetch(`/public/note/burn/${slug}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // Add CSRF token here if your backend requires it:
        // 'X-CSRF-TOKEN': 'token_value'
      }
    })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.status === 'ok') {
          toastr.success(data.message, 'Success');
          shatterEffect();
        } else {
          toastr.error(data.message || 'An error occurred while burning the note.', 'Error');
        }
      })
      .catch(error => {
        toastr.error('Error burning the note: ' + error.message, 'Error');
      });
  }

  /**
   * Update the countdown timer every second.
   * When timer reaches zero, it sends the burn request.
   */
  function updateTimer() {

    if (remainingSeconds <= 0) {
      countdownEl.textContent = '00:00';
      clearInterval(timerInterval);

      // Trigger burning of the note on the backend and animation
      burnNote(noteSlug);
      return;
    }

    countdownEl.textContent = formatTime(remainingSeconds);
    remainingSeconds--;
  }

  // Start the countdown immediately and update every second
  updateTimer();
  const timerInterval = setInterval(updateTimer, 1000);
});
