(function () {
    var form = document.getElementById('gallery-like-form');
    var btn = document.getElementById('gallery-like-submit');
    var countEl = document.getElementById('gallery-like-count');
    if (!form || !btn || !countEl) {
        return;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (btn.disabled) {
            return;
        }
        btn.disabled = true;

        var fd = new FormData(form);
        fetch(form.getAttribute('action') || '/gallery/like', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            redirect: 'follow',
        })
            .then(function (response) {
                var url = response.url || '';
                if (url.indexOf('/login') !== -1) {
                    window.location.href = url;
                    return null;
                }
                var ct = response.headers.get('Content-Type') || '';
                if (!response.ok || ct.indexOf('application/json') === -1) {
                    window.location.reload();
                    return null;
                }
                return response.json();
            })
            .then(function (data) {
                if (data === null) {
                    return;
                }
                if (!data || typeof data.like_count === 'undefined') {
                    window.location.reload();
                    return;
                }
                countEl.textContent = String(data.like_count);
                var liked = Boolean(data.liked);
                btn.textContent = liked ? 'Unlike' : 'Like';
                btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
            })
            .catch(function () {
                window.location.reload();
            })
            .finally(function () {
                btn.disabled = false;
            });
    });
})();
