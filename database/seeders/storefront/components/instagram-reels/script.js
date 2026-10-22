/*
 * The Instagram reels: a swipeable row of muted clips linking to the store's Instagram. Every clip
 * plays, looping, while it is on screen, in the page editor too, and pauses off screen. Keyboard
 * focus on a reel pauses them all, so the looping video can always be stopped; with reduced motion
 * or data saver on, the posters stay. The <video> elements are made here, so the page editor never
 * turns the clips into its own video components (which add player controls and drop the clip).
 */
var root = this;

Store.ready(function () {
  Store.bind(root);
  var calm = window.matchMedia('(prefers-reduced-motion: reduce)');
  var saveData = navigator.connection && navigator.connection.saveData;
  var reels = Array.prototype.slice.call(root.querySelectorAll('.reel[data-video]'));
  var videos = [];
  var onScreen = reels.map(function () { return !('IntersectionObserver' in window); });
  var focused = false;

  function videoOf(index) {
    if (!videos[index]) {
      var reel = reels[index];
      var video = videos[index] = document.createElement('video');
      video.muted = true;
      video.loop = true;
      video.playsInline = true;
      video.setAttribute('playsinline', '');
      video.setAttribute('aria-hidden', 'true');
      video.src = reel.getAttribute('data-video');
      video.addEventListener('playing', function () { video.classList.add('is-playing'); });
      reel.insertBefore(video, reel.querySelector('.reel-badge'));
    }
    return videos[index];
  }

  function sync() {
    var allowed = !focused && !calm.matches && !saveData && !document.hidden;
    reels.forEach(function (reel, index) {
      if (allowed && onScreen[index]) {
        videoOf(index).play().catch(function () {});
      } else if (videos[index]) {
        videos[index].pause();
        videos[index].classList.remove('is-playing');
      }
    });
  }

  if ('IntersectionObserver' in window) {
    var watcher = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) { onScreen[reels.indexOf(entry.target)] = entry.isIntersecting; });
      sync();
    }, { threshold: 0.5 });
    reels.forEach(function (reel) { watcher.observe(reel); });
  }

  root.addEventListener('focusin', function (event) {
    focused = event.target.matches(':focus-visible');
    sync();
  });
  root.addEventListener('focusout', function (event) {
    if (!root.contains(event.relatedTarget)) {
      focused = false;
      sync();
    }
  });
  document.addEventListener('visibilitychange', sync);
  if (calm.addEventListener) calm.addEventListener('change', sync);
  sync();
});
