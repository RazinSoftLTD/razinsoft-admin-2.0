{{-- Live refresh: re-fetches this page and swaps #live-region when a new visit is logged
     (Reverb websocket via the layout's shared Pusher client), plus a 30s safety poll.
     Swapping innerHTML keeps scroll position — no full page reload. --}}
<script>
    (function () {
        let pending = false;
        let last = 0;

        async function refresh() {
            const now = Date.now();
            if (pending || now - last < 4000) return; // debounce bursts of visits
            pending = true;
            try {
                const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'fetch' } });
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const fresh = doc.getElementById('live-region');
                const cur = document.getElementById('live-region');
                if (fresh && cur) cur.innerHTML = fresh.innerHTML;
                last = Date.now();
                const dot = document.getElementById('live-ping');
                if (dot) { dot.classList.add('animate-ping'); setTimeout(() => dot.classList.remove('animate-ping'), 1200); }
            } catch (e) { /* keep the current view */ } finally { pending = false; }
        }

        function bind() {
            if (window.Razin && window.Razin.pusher) {
                const ch = window.Razin.pusher.subscribe('activity.visits');
                ch.unbind('logged', refresh);
                ch.bind('logged', refresh);
                const badge = document.getElementById('live-badge');
                if (badge) { badge.classList.remove('hidden'); badge.classList.add('inline-flex'); }
            } else {
                setTimeout(bind, 1500); // pusher script loads async
            }
        }
        bind();

        // Safety net: refresh every 30s even if the socket misses an event.
        setInterval(refresh, 30000);
    })();
</script>
