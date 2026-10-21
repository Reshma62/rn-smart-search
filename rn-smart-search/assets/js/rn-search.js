/* RN Smart Search — live preview + navigation */
(function () {
  "use strict";

  if (typeof RNSmartSearch === "undefined") {
    console.log("[RN Search] RNSmartSearch global missing, aborting");
    return;
  }

  var DEBOUNCE = 280;

  // Header term for product grids WITHOUT a JSF search filter. null = the
  // header never searched on this page, so JSF requests stay untouched.
  var rnGridTerm = null;
  // Header category for CSS-ID grids (null = not sent).
  var rnGridCat = null;
  // Query ids (grid CSS IDs) whose JSF requests carry rn_s / rn_cat. Any
  // other listing on the page (mega menu, popups…) is never touched.
  var rnGridIds = {};
  // Shop / archive product grid(s), from WooCommerce → Settings → Products.
  var SHOP_IDS = RNSmartSearch.shopGridIds || [];

  /** Query id of a JSF request ("jet-engine/all-products" → "all-products"). */
  function requestQueryId(data) {
    var m = /(?:^|&)provider=([^&]*)/.exec(data);
    if (!m) {
      return "";
    }
    var provider = decodeURIComponent(m[1].replace(/\+/g, " "));
    return provider.split("/").pop();
  }

  // ?rn_debug=1 in the page URL → console report of every listing query.
  var DEBUG = /[?&]rn_debug=1\b/.test(window.location.search);

  // jQuery hooks are set up once jQuery exists — immediately, or at
  // DOMContentLoaded when this file runs deferred before a late jQuery.
  var jqReady = false;
  function setupJQuery() {
    if (jqReady || !window.jQuery) {
      return;
    }
    jqReady = true;
    bindPrefilter();
    bindDebugAjax();
    bindElementorHook();
  }

  // Attach the term to every JetSmartFilters request (filter, sort, page),
  // so the search sticks while the visitor ticks filters or paginates.
  function bindPrefilter() {
    window.jQuery.ajaxPrefilter(function (opts) {
      if (
        typeof opts.data !== "string" ||
        opts.data.indexOf("action=jet_smart_filters") === -1
      ) {
        return;
      }
      if (rnGridIds[requestQueryId(opts.data)]) {
        if (rnGridTerm !== null) {
          opts.data += "&rn_s=" + encodeURIComponent(rnGridTerm);
        }
        if (rnGridCat !== null) {
          opts.data += "&rn_cat=" + encodeURIComponent(rnGridCat);
        }
      }
      if (DEBUG) {
        opts.data += "&rn_debug=1";
      }
    });
  }

  /**
   * Selected categories in the JSF sidebar (checkbox / radio / select).
   */
  function debugSelectedCats() {
    var out = [];
    document
      .querySelectorAll(
        'input[name*="product_cat"]:checked, select[name*="product_cat"]',
      )
      .forEach(function (el) {
        if (el.tagName === "SELECT") {
          if (el.value) {
            out.push({
              id: el.value,
              name: el.options[el.selectedIndex].textContent.trim(),
            });
          }
          return;
        }
        out.push({
          id: el.value,
          name: String(jsfLabelOf(el))
            .replace(/\(\s*\d+\s*\)\s*$/, "")
            .trim(),
        });
      });
    return out;
  }

  function debugReport(label, records) {
    if (!DEBUG) {
      return;
    }
    var cats = debugSelectedCats();
    console.group("[RN Debug] " + label);
    console.log(
      "Selected categories (" + cats.length + "):",
      cats.length
        ? cats
            .map(function (c) {
              return c.name + " (#" + c.id + ")";
            })
            .join(", ")
        : "(none)",
    );
    if (!records || !records.length) {
      console.log("No product listing query was reported by the server.");
    }
    (records || []).forEach(function (r, i) {
      console.group(
        "Query " + (i + 1) + " [" + r.source + "] → " + r.found_posts +
          " products found" + (r.search ? ' (search "' + r.search + '")' : ""),
      );
      console.log("tax_query:");
      console.table(r.tax_query);
      if (r.per_category && Object.keys(r.per_category).length) {
        console.log("Products per selected category (all pages):");
        console.table(r.per_category);
      }
      console.log("Products on this page (" + r.products.length + "):");
      console.table(r.products);
      console.groupEnd();
    });
    console.groupEnd();
  }

  if (DEBUG) {
    window.addEventListener("load", function () {
      debugReport("page load", window.RNSearchDebug || []);
    });
  }

  function bindDebugAjax() {
    if (DEBUG) {
      window.jQuery(document).ajaxComplete(function (e, xhr, opts) {
        if (
          typeof opts.data !== "string" ||
          !/(^|&)action=jet_smart_filters(&|$)/.test(opts.data)
        ) {
          return;
        }
        var res = xhr.responseJSON;
        if (!res) {
          try {
            res = JSON.parse(xhr.responseText);
          } catch (err) {
            res = null;
          }
        }
        debugReport(
          "JetSmartFilters AJAX",
          res && res.rn_debug ? res.rn_debug : [],
        );
      });
    }
  }

  // Console logs only in debug mode (?rn_debug=1) — logging on every
  // keystroke costs time and clutters the console on production.
  var LOG_ON = /[?&]rn_debug=1\b/.test(window.location.search);

  function LOG() {
    if (!LOG_ON) {
      return;
    }
    var args = Array.prototype.slice.call(arguments);
    args.unshift("[RN Search]");
    console.log.apply(console, args);
  }

  /**
   * Normalise a label for comparison: strip trailing "(12)" counts,
   * collapse whitespace, lowercase.
   */
  function norm(s) {
    return String(s || "")
      .replace(/\(\s*\d+\s*\)\s*$/, "")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();
  }

  /**
   * Build a URL to the shop / search results page.
   */
  function buildShopUrl(term, category) {
    var url = new URL("/", window.location.origin);
    url.searchParams.set("post_type", "product");
    if (term) {
      url.searchParams.set("s", term);
    }
    if (category) {
      url.searchParams.set("product_cat", category);
    }
    return url.toString();
  }

  function esc(str) {
    var d = document.createElement("div");
    d.textContent = str == null ? "" : String(str);
    return d.innerHTML;
  }

  /**
   * Search state that came from the query string (search results page).
   * `hasJsf` = JetSmartFilters already restores its own state from the URL
   * (?jsf=...&tax=...), so we must not interfere.
   */
  function getUrlState() {
    var p = new URLSearchParams(window.location.search);
    return {
      s: (p.get("s") || "").trim(),
      cat: (p.get("product_cat") || "").trim(),
      hasJsf: p.has("jsf"),
    };
  }

  /**
   * Label text of a JSF checkbox / radio input.
   */
  function jsfLabelOf(inp) {
    var attr = inp.getAttribute("data-label");
    if (attr) {
      return attr;
    }
    var row =
      inp.closest("label") ||
      inp.closest(".jet-checkboxes-list__row") ||
      inp.closest(".jet-radio-list__row") ||
      inp.parentNode;
    var el = row
      ? row.querySelector(".jet-checkboxes-list__label, .jet-radio-list__label")
      : null;
    return (el || row || inp).textContent;
  }

  // ------------------------------------------------------------------
  // JSF debug: log every JSF AJAX cycle.
  // ------------------------------------------------------------------
  var jsfLogsBound = false;
  function bindJsfLogs(tries) {
    if (jsfLogsBound) {
      return;
    }
    try {
      if (
        window.JetSmartFilters &&
        window.JetSmartFilters.events &&
        typeof window.JetSmartFilters.events.subscribe === "function"
      ) {
        [
          "ajaxFilters/start-loading",
          "ajaxFilters/updated",
          "ajaxFilters/end-loading",
          "ajaxFilters/end",
        ].forEach(function (evt) {
          window.JetSmartFilters.events.subscribe(evt, function () {
            LOG("JSF event:", evt, Array.prototype.slice.call(arguments));
          });
        });
        jsfLogsBound = true;
        LOG("JSF event logging bound");
        return;
      }
    } catch (e) {
      LOG("JSF log bind error", e);
    }
    if (tries > 0) {
      window.setTimeout(function () {
        bindJsfLogs(tries - 1);
      }, 500);
    }
  }
  bindJsfLogs(20);

  function initInstance(root) {
    if (root.dataset.rnInit === "1") {
      return;
    }
    root.dataset.rnInit = "1";

    var form = root.querySelector(".rn-search__bar");
    var input = root.querySelector(".rn-search__input");
    var preview = root.querySelector(".rn-search__preview");
    var list = root.querySelector(".rn-search__preview-list");

    var cat = root.querySelector(".rn-search__cat");
    var catValue = root.querySelector(".rn-search__cat-value");
    var catTrigger = root.querySelector(".rn-search__cat-trigger");
    var catLabel = root.querySelector(".rn-search__cat-label");
    var catPanel = root.querySelector(".rn-search__cat-panel");
    var options = catPanel
      ? catPanel.querySelectorAll(".rn-search__cat-option")
      : [];

    var showPreview = root.dataset.preview === "1" && preview && list;
    var showHistory = root.dataset.history === "1" && preview;
    var histBox = root.querySelector(".rn-search__history");
    var histList = root.querySelector(".rn-search__history-list");
    var HIST_KEY = "rn_search_history";
    var HIST_MAX = 8;
    var limit = parseInt(root.dataset.limit, 10) || 5;
    var showTotal = root.dataset.showTotal === "1";
    var totalText = root.dataset.totalText || "%s results found";
    var showThumb = root.dataset.showThumb !== "0";
    var showPrice = root.dataset.showPrice !== "0";
    // Guests get prices only when the widget allows it (guest_price=1);
    // the server hides them by default, so a stale cached JS stays safe.
    var guestPrice = root.dataset.priceLogin === "0" ? "1" : "0";
    var showCat = root.dataset.showCat === "1";
    var listingId = root.dataset.listing || "0";
    var liveFilter = root.dataset.live || "0";

    var timer = null;
    var controller = null;

    // true once the visitor picked a category in the HEADER dropdown; only
    // then do we push the category into JetSmartFilters.
    var catDirty = false;

    LOG("init instance", root.id, {
      liveFilter: liveFilter,
      showPreview: !!showPreview,
    });

    function currentCategory() {
      return catValue ? catValue.value : "";
    }

    function openCat() {
      if (!cat || !catPanel) {
        return;
      }
      catPanel.hidden = false;
      cat.setAttribute("data-open", "1");
      if (catTrigger) {
        catTrigger.setAttribute("aria-expanded", "true");
      }
    }

    function closeCat() {
      if (!cat || !catPanel) {
        return;
      }
      catPanel.hidden = true;
      cat.setAttribute("data-open", "0");
      if (catTrigger) {
        catTrigger.setAttribute("aria-expanded", "false");
      }
    }

    function openPreview() {
      if (preview) {
        preview.hidden = false;
      }
    }

    function closePreview() {
      if (preview) {
        preview.hidden = true;
      }
    }

    /**
     * Select a category option in the header dropdown (UI + hidden value).
     */
    function setCategory(opt) {
      if (!opt) {
        return;
      }
      var value = opt.getAttribute("data-value") || "";
      if (catValue) {
        catValue.value = value;
      }
      if (catLabel) {
        catLabel.textContent = opt.textContent.trim();
      }
      Array.prototype.forEach.call(options, function (o) {
        o.classList.toggle("is-active", o === opt);
      });
      LOG("category set", value || "(all)");
    }

    function setCategoryBySlug(slug) {
      for (var i = 0; i < options.length; i++) {
        if ((options[i].getAttribute("data-value") || "") === slug) {
          setCategory(options[i]);
          return true;
        }
      }
      LOG("category slug not found in dropdown", slug);
      return false;
    }

    // ---- Recent searches (localStorage) ----
    function getHistory() {
      try {
        return JSON.parse(window.localStorage.getItem(HIST_KEY) || "[]") || [];
      } catch (e) {
        return [];
      }
    }
    function saveHistory(arr) {
      try {
        window.localStorage.setItem(
          HIST_KEY,
          JSON.stringify(arr.slice(0, HIST_MAX)),
        );
      } catch (e) {}
    }
    function addHistory(term) {
      term = (term || "").trim();
      if (term.length < 2) {
        return;
      }
      var arr = getHistory().filter(function (t) {
        return t.toLowerCase() !== term.toLowerCase();
      });
      arr.unshift(term);
      saveHistory(arr);
    }
    function removeHistory(term) {
      saveHistory(
        getHistory().filter(function (t) {
          return t !== term;
        }),
      );
    }
    function renderHistory() {
      if (!showHistory || !histBox || !histList) {
        return false;
      }
      var arr = getHistory();
      if (!arr.length) {
        histBox.hidden = true;
        return false;
      }
      histList.innerHTML = "";
      arr.forEach(function (term) {
        var row = document.createElement("div");
        row.className = "rn-search__hist-item";
        row.setAttribute("data-term", term);
        row.innerHTML =
          '<span class="rn-search__hist-term"></span>' +
          '<button type="button" class="rn-search__hist-remove" aria-label="Remove">\u00d7</button>';
        row.querySelector(".rn-search__hist-term").textContent = term;
        histList.appendChild(row);
      });
      histBox.hidden = false;
      return true;
    }
    function runHistoryTerm(term) {
      if (!input || !term) {
        return;
      }
      input.value = term;
      addHistory(term);
      if (canLiveFilter()) {
        if (histBox) {
          histBox.hidden = true;
        }
        closePreview();
        tryLiveFilter(term);
        return;
      }
      if (form) {
        form.dispatchEvent(
          new Event("submit", { bubbles: true, cancelable: true }),
        );
      }
    }

    function setState(html) {
      if (!list) {
        return;
      }
      list.innerHTML = '<div class="rn-search__state">' + html + "</div>";
      openPreview();
    }

    function renderLoading() {
      if (!list) {
        return;
      }
      var rows = Math.max(2, Math.min(limit || 4, 4));
      var html =
        '<div class="rn-search__loading" role="status" aria-label="' +
        esc(RNSmartSearch.i18n.searching) +
        '">';
      for (var i = 0; i < rows; i++) {
        html +=
          '<div class="rn-search__skel-row">' +
          '<span class="rn-search__skel rn-search__skel-thumb"></span>' +
          '<span class="rn-search__skel-body">' +
          '<span class="rn-search__skel rn-search__skel-line" style="width:70%"></span>' +
          '<span class="rn-search__skel rn-search__skel-line" style="width:35%"></span>' +
          "</span>" +
          "</div>";
      }
      html += "</div>";
      list.innerHTML = html;
      openPreview();
    }

    function renderResults(products, term, total) {
      if (!list) {
        return;
      }
      if (!products.length) {
        setState(esc(RNSmartSearch.i18n.noResults));
        return;
      }

      var html = "";

      if (showTotal && total > 0) {
        var totalStr;
        try {
          totalStr = Number(total).toLocaleString();
        } catch (e) {
          totalStr = String(total);
        }
        html +=
          '<div class="rn-search__preview-head">' +
          esc(totalText).replace("%s", "<b>" + esc(totalStr) + "</b>") +
          "</div>";
      }

      products.forEach(function (p) {
        if (p.html) {
          html +=
            '<div class="rn-search__result rn-search__result--custom">' +
            p.html +
            "</div>";
          return;
        }

        var thumb = showThumb
          ? '<img class="rn-search__result-thumb" src="' +
            esc(p.image) +
            '" alt="" loading="lazy" />'
          : "";
        var pcat =
          showCat && p.category
            ? '<span class="rn-search__result-cat">' +
              esc(p.category) +
              "</span>"
            : "";
        var price =
          showPrice && p.price
            ? '<span class="rn-search__result-price">' + p.price + "</span>"
            : "";

        html +=
          '<a class="rn-search__result" href="' +
          esc(p.url) +
          '">' +
          thumb +
          '<span class="rn-search__result-body">' +
          pcat +
          '<span class="rn-search__result-title">' +
          esc(p.title) +
          "</span>" +
          price +
          "</span>" +
          "</a>";
      });

      html +=
        '<a class="rn-search__viewall" href="' +
        esc(buildShopUrl(term, currentCategory())) +
        '">' +
        esc(RNSmartSearch.i18n.viewAll) +
        "</a>";

      list.innerHTML = html;
      openPreview();
    }

    // Small per-page cache of preview responses (most recent 40 searches).
    var previewCache = {};
    var previewCacheKeys = [];
    function rememberPreview(key, data) {
      if (!previewCache[key]) {
        previewCacheKeys.push(key);
        if (previewCacheKeys.length > 40) {
          delete previewCache[previewCacheKeys.shift()];
        }
      }
      previewCache[key] = data;
    }

    function fetchPreview(term) {
      if (controller) {
        controller.abort();
      }

      // Same term/category typed again (or back-spaced to) → instant, no request.
      var cacheKey = [term.toLowerCase(), currentCategory(), limit].join("|");
      if (previewCache[cacheKey]) {
        var hit = previewCache[cacheKey];
        LOG("preview from browser cache", cacheKey);
        renderResults(hit.products, term, hit.total);
        return;
      }

      controller = new AbortController();

      var params = new URLSearchParams({
        action: "rn_smart_search",
        nonce: RNSmartSearch.nonce,
        term: term,
        category: currentCategory(),
        limit: String(limit),
        listing: listingId,
        guest_price: guestPrice,
      });

      LOG("preview fetch", { term: term, category: currentCategory() });
      renderLoading();

      fetch(RNSmartSearch.ajaxUrl + "?" + params.toString(), {
        method: "GET",
        credentials: "same-origin",
        signal: controller.signal,
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          LOG("preview response", res);
          if (res && res.success && res.data) {
            rememberPreview(cacheKey, {
              products: res.data.products || [],
              total: res.data.total || 0,
            });
            renderResults(res.data.products || [], term, res.data.total || 0);
          } else {
            setState(esc(RNSmartSearch.i18n.noResults));
          }
        })
        .catch(function (err) {
          if (err && err.name === "AbortError") {
            return;
          }
          LOG("preview error", err);
          setState(esc(RNSmartSearch.i18n.noResults));
        });
    }

    // ------------------------------------------------------------------
    // JetSmartFilters bridge
    // ------------------------------------------------------------------

    function jsfSearchInput() {
      return document.querySelector(".jet-search-filter__input");
    }

    // A JSF search filter is on THIS page → live-filter it as you type.
    function canLiveFilter() {
      if (targetGrids().length) {
        return true;
      }
      return (
        liveFilter === "1" &&
        typeof window.JetSmartFilters !== "undefined" &&
        (!!jsfSearchInput() || gridSearchReady())
      );
    }

    // ---- Listing grids targeted by CSS ID (widget setting) ----
    var gridIds = (root.dataset.gridIds || "")
      .split(",")
      .map(function (s) {
        return s.trim();
      })
      .filter(Boolean);
    var gridSeq = {}; // latest request number per grid ID

    /**
     * Every Listing Grid on this page whose CSS ID is in the widget's list
     * (several IDs → all matching grids are searched together).
     *
     * @return {Array} [{ id, el }]
     */
    function targetGrids() {
      var found = [];
      gridIds.forEach(function (id) {
        var el = document.getElementById(id);
        if (el && el.querySelector(".jet-listing-grid")) {
          found.push({ id: id, el: el });
        }
      });
      return found;
    }

    /**
     * Live-search the targeted grid. With a JSF group for it, the term rides
     * along JSF's request (keeps the page's filters); otherwise the server
     * re-renders just that grid with the term.
     */
    /**
     * Grid fed from a JetEngine front-end data store (Local Storage /
     * Session)? Its query carries post__in = ["is-front", type, slug] on first
     * render; remembered per grid because our re-render replaces that markup.
     *
     * @return {Object|null} { type, slug, ids }
     */
    var frontStores = {};
    function frontStoreOf(grid) {
      if (!frontStores[grid.id]) {
        var items = grid.el.querySelector(".jet-listing-grid__items");
        var nav = null;
        try {
          nav = items ? JSON.parse(items.getAttribute("data-nav") || "null") : null;
        } catch (e) {
          nav = null;
        }
        var query = (nav && nav.query) || {};
        var postIn = query.post__in;
        if (postIn && postIn[0] === "is-front") {
          // First render: ["is-front", type, slug].
          frontStores[grid.id] = { type: postIn[1], slug: postIn[2] };
        } else if (query.front_store__in) {
          // Already loaded by JetEngine: the store name is gone from the
          // query — take it from a wishlist button in the cards, or from the
          // only registered front-end store.
          var info = null;
          var btn = grid.el.querySelector(".jet-data-store-link[data-args]");
          if (btn) {
            try {
              var bargs = JSON.parse(btn.getAttribute("data-args"));
              if (bargs && bargs.store && bargs.store.slug) {
                info = { type: bargs.store.type, slug: bargs.store.slug };
              }
            } catch (e) {
              info = null;
            }
          }
          if (!info && window.JetEngineRegisteredStores) {
            var fronts = Object.keys(window.JetEngineRegisteredStores).filter(
              function (slug) {
                var t = window.JetEngineRegisteredStores[slug];
                return t === "local-storage" || t === "session";
              },
            );
            if (fronts.length === 1) {
              info = {
                type: window.JetEngineRegisteredStores[fronts[0]],
                slug: fronts[0],
              };
            }
          }
          if (!info) {
            // Last resort: the ids JetEngine loaded with.
            return {
              type: "",
              slug: "",
              ids: [].concat(query.front_store__in)
                .map(function (id) {
                  return parseInt(id, 10);
                })
                .filter(function (id) {
                  return id > 0;
                }),
            };
          }
          frontStores[grid.id] = info;
        } else {
          return null;
        }
      }
      var info = frontStores[grid.id];
      var ids = [];
      var stores = window.JetEngineStores || {};
      try {
        if (stores[info.type] && stores[info.type].getStore) {
          ids = stores[info.type].getStore(info.slug) || [];
        } else {
          var raw =
            info.type === "session"
              ? window.sessionStorage.getItem("jet_engine_store_" + info.slug)
              : window.localStorage.getItem("jet_engine_store_" + info.slug);
          ids = (raw || "").split(",");
        }
      } catch (e) {
        ids = [];
      }
      ids = ids
        .map(function (id) {
          return parseInt(id, 10);
        })
        .filter(function (id) {
          return id > 0;
        });
      return { type: info.type, slug: info.slug, ids: ids };
    }

    targetGrids().forEach(frontStoreOf);

    // ---- "No results" message under live-searched grids ----
    var emptyOn = root.hasAttribute("data-empty-text");
    var emptyText = root.getAttribute("data-empty-text") || "";
    var emptyElId = root.getAttribute("data-empty-el") || "";
    var widgetId = root.getAttribute("data-wid") || "";
    var inEditor = document.body.classList.contains("elementor-editor-active");
    var termNodes = null; // text nodes of the custom section containing {term}

    function customEmptyEl() {
      return emptyElId ? document.getElementById(emptyElId) : null;
    }

    // The custom section stays hidden until a search finds nothing (it is
    // visible in the Elementor editor so it can be designed).
    if (emptyOn && !inEditor && customEmptyEl()) {
      customEmptyEl().style.display = "none";
    }

    function fillTerm(el, term) {
      if (null === termNodes) {
        termNodes = [];
        var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
        var node;
        while ((node = walker.nextNode())) {
          if (node.nodeValue.indexOf("{term}") !== -1) {
            termNodes.push({ node: node, text: node.nodeValue });
          }
        }
      }
      termNodes.forEach(function (t) {
        t.node.nodeValue = t.text.split("{term}").join(term);
      });
    }

    /**
     * Show / hide the "no results" message for one grid after a search.
     *
     * @param {Element} gridEl Listing grid widget element (by CSS ID).
     * @param {string}  term   Current search term.
     */
    function updateEmptyState(gridEl, term) {
      if (!emptyOn || !gridEl) {
        return;
      }
      var noResults =
        !!term && !gridEl.querySelector(".jet-listing-grid__item");
      gridEl.classList.toggle("rn-grid--no-results", noResults);
      document.body.classList.toggle("rn-search-no-results", noResults);

      var custom = customEmptyEl();
      if (custom) {
        if (noResults) {
          fillTerm(custom, term);
          custom.style.display = "";
        } else if (!inEditor) {
          custom.style.display = "none";
        }
        return;
      }
      if (!emptyText) {
        return;
      }
      var msg = gridEl.nextElementSibling;
      if (!msg || !msg.classList.contains("rn-grid-empty")) {
        msg = document.createElement("div");
        msg.className = "rn-grid-empty rn-grid-empty--" + widgetId;
        msg.setAttribute("role", "status");
        msg.hidden = true;
        gridEl.insertAdjacentElement("afterend", msg);
      }
      msg.textContent = emptyText.split("{term}").join(term);
      msg.hidden = !noResults;
    }

    // JetSmartFilters re-rendered a grid this widget drives (shop / CSS IDs).
    if (emptyOn && window.jQuery) {
      window.jQuery(document).on(
        "jet-filter-content-rendered",
        function (e, $provider, group, provider, queryId) {
          var id = queryId || (group && group.queryId) || "";
          if (
            id &&
            (SHOP_IDS.indexOf(id) !== -1 || gridIds.indexOf(id) !== -1)
          ) {
            updateEmptyState(
              document.getElementById(id),
              input ? input.value.trim() : "",
            );
          }
        },
      );
    }

    function searchTargetGrid(grid, term) {
      var group = jsfGroups().filter(function (g) {
        return g.queryId === grid.id;
      })[0];

      if (group && window.jQuery) {
        LOG("target grid via JSF group", grid.id, term);
        rnGridTerm = term;
        rnGridCat = currentCategory();
        rnGridIds[grid.id] = true;
        if (typeof group.resetFiltersByName === "function") {
          group.resetFiltersByName("pagination");
        }
        group.currentQuery = { rn_force: Date.now() };
        group.doAjax();
        return;
      }

      var holder = grid.el.closest("[data-elementor-id]");
      if (!holder) {
        LOG("target grid has no Elementor document", grid.id);
        return;
      }

      var seq = (gridSeq[grid.id] = (gridSeq[grid.id] || 0) + 1);
      var body = new URLSearchParams({
        action: "rn_smart_search_grid",
        nonce: RNSmartSearch.nonce,
        doc_id: holder.getAttribute("data-elementor-id"),
        grid_id: grid.id,
        term: term,
        category: currentCategory(),
      });

      // Browser-side data store (Local Storage / Session wishlist): the server
      // can't see it, so send the saved product ids along.
      var front = frontStoreOf(grid);
      if (front) {
        body.append("front_store", "1");
        body.append("front_ids", front.ids.join(","));
      }
      LOG("target grid via AJAX render", grid.id, term);
      grid.el.classList.add("rn-grid--loading");

      fetch(RNSmartSearch.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          if (seq !== gridSeq[grid.id]) {
            return; // an older request finished after a newer one
          }
          var current = document.getElementById(grid.id) || grid.el;
          current.classList.remove("rn-grid--loading");
          if (!res || !res.success || !res.data || !res.data.html) {
            LOG("grid render failed", res);
            return;
          }
          var tpl = document.createElement("template");
          tpl.innerHTML = res.data.html.trim();
          var fresh = tpl.content.querySelector("#" + CSS.escape(grid.id));
          if (!fresh) {
            LOG("grid render returned no #" + grid.id);
            return;
          }
          // Inline <style>/<link> the render printed (listing item CSS).
          Array.prototype.forEach.call(
            tpl.content.querySelectorAll("style, link[rel=stylesheet]"),
            function (st) {
              if (!fresh.contains(st)) {
                document.head.appendChild(st);
              }
            },
          );
          current.replaceWith(fresh);
          if (
            window.elementorFrontend &&
            window.elementorFrontend.elementsHandler &&
            window.jQuery
          ) {
            window.elementorFrontend.elementsHandler.runReadyTrigger(
              window.jQuery(fresh),
            );
          }
          updateEmptyState(fresh, term);
          // Wishlist / compare buttons on the new cards: restore their
          // "added" state from the browser store.
          if (
            window.JetEngine &&
            typeof window.JetEngine.initFrontStores === "function" &&
            window.jQuery
          ) {
            window.JetEngine.initFrontStores(window.jQuery(fresh));
          }
          // Let site scripts re-apply their tweaks to the new cards
          // (JSF fires "jet-filter-content-rendered" for its own renders).
          if (window.jQuery) {
            window.jQuery(document).trigger("rn-search-grid-rendered", [
              window.jQuery(fresh),
              grid.id,
            ]);
          }
          document.dispatchEvent(
            new CustomEvent("rn-search-grid-rendered", {
              detail: { grid: fresh, id: grid.id },
            }),
          );
        })
        .catch(function (err) {
          if (seq === gridSeq[grid.id]) {
            grid.el.classList.remove("rn-grid--loading");
          }
          LOG("grid render error", err);
        });
    }

    /**
     * Product archive with JSF filters but NO JSF search filter: the term is
     * sent along with JSF's own AJAX request (rn_s, see the prefilter below)
     * and PHP applies it as a title/SKU search.
     */
    function jsfGroups() {
      var jsf = window.JetSmartFilters;
      if (!jsf || !jsf.filterGroups) {
        return [];
      }
      return Object.keys(jsf.filterGroups)
        .map(function (k) {
          return jsf.filterGroups[k];
        })
        .filter(function (g) {
          return g && typeof g.doAjax === "function";
        });
    }

    /** JSF groups of the shop grid(s) only (by CSS ID). */
    function shopGroups() {
      return jsfGroups().filter(function (g) {
        return SHOP_IDS.indexOf(g.queryId) !== -1;
      });
    }

    function gridSearchReady() {
      return (
        !!RNSmartSearch.productArchive &&
        !!window.jQuery &&
        shopGroups().length > 0
      );
    }

    /**
     * Locate the JSF category control. Supports <select>, checkboxes and
     * radios. First by name (contains "product_cat"), then by matching the
     * option labels against the header dropdown's category names.
     *
     * @return {Object|null} { type: 'select', el } | { type: 'inputs', list }
     */
    function findJsfCategory() {
      var sel = document.querySelector('select[name*="product_cat"]');
      if (sel) {
        return { type: "select", el: sel };
      }

      var byName = document.querySelectorAll(
        'input[type="checkbox"][name*="product_cat"], input[type="radio"][name*="product_cat"]',
      );
      if (byName.length) {
        return { type: "inputs", list: Array.prototype.slice.call(byName) };
      }

      // Fallback: match by labels.
      var names = {};
      Array.prototype.forEach.call(options, function (o) {
        if (o.getAttribute("data-value")) {
          names[norm(o.textContent)] = 1;
        }
      });

      var best = null;
      var bestScore = 0;

      Array.prototype.forEach.call(
        document.querySelectorAll(".jet-checkboxes-list, .jet-radio-list"),
        function (g) {
          var l = Array.prototype.slice.call(
            g.querySelectorAll('input[type="checkbox"], input[type="radio"]'),
          );
          var score = l.filter(function (i) {
            return names[norm(jsfLabelOf(i))];
          }).length;
          if (score > bestScore) {
            best = { type: "inputs", list: l };
            bestScore = score;
          }
        },
      );

      Array.prototype.forEach.call(
        document.querySelectorAll(
          ".jet-smart-filters-select select, select.jet-select__control",
        ),
        function (s) {
          var score = 0;
          for (var i = 0; i < s.options.length; i++) {
            if (names[norm(s.options[i].textContent)]) {
              score++;
            }
          }
          if (score > bestScore) {
            best = { type: "select", el: s };
            bestScore = score;
          }
        },
      );

      return bestScore >= 2 ? best : null;
    }

    /**
     * Push the header's selected category into the JSF category filter.
     * Header stores slugs, JSF uses term ids → we match by label.
     *
     * Safety rules:
     *  - a header category that has NO matching JSF option leaves JSF
     *    completely untouched (never wipes the visitor's own ticks);
     *  - header "All" clears the JSF category ticks (only when the visitor
     *    actually chose "All", see catDirty).
     *
     * @param {boolean} force Fire the JSF change event even when the control
     *                        already shows the wanted state.
     * @return {boolean} true if a JSF category control was found.
     */
    function driveJsfCategory(force) {
      var ctl = findJsfCategory();
      if (!ctl) {
        LOG("no JSF category filter found on page");
        return false;
      }

      var wanted = "";
      if (currentCategory()) {
        var active = root.querySelector(".rn-search__cat-option.is-active");
        wanted = active ? norm(active.textContent) : "";
      }

      // Does JSF have an option for the wanted header category?
      var hasMatch = false;
      if (wanted) {
        if (ctl.type === "select") {
          for (var k = 0; k < ctl.el.options.length; k++) {
            if (norm(ctl.el.options[k].textContent) === wanted) {
              hasMatch = true;
              break;
            }
          }
        } else {
          hasMatch = ctl.list.some(function (inp) {
            return norm(jsfLabelOf(inp)) === wanted;
          });
        }
        if (!hasMatch) {
          LOG(
            "header category has no matching JSF option, leaving JSF untouched:",
            wanted,
          );
          return true;
        }
      }

      LOG("driveJsfCategory", {
        type: ctl.type,
        wanted: wanted || "(all)",
        force: !!force,
      });

      if (ctl.type === "select") {
        var val = "";
        for (var i = 0; i < ctl.el.options.length; i++) {
          if (wanted && norm(ctl.el.options[i].textContent) === wanted) {
            val = ctl.el.options[i].value;
            break;
          }
        }
        if (ctl.el.value !== val || force) {
          ctl.el.value = val;
          ctl.el.dispatchEvent(new Event("change", { bubbles: true }));
          LOG("JSF select change fired →", val || "(all)");
        }
        return true;
      }

      // Checkboxes / radios: set every state first, then fire ONE change so
      // JSF sends a single request with the final combination.
      var changedEl = null;
      ctl.list.forEach(function (inp) {
        var should = !!wanted && norm(jsfLabelOf(inp)) === wanted;
        if (inp.checked !== should) {
          inp.checked = should;
          changedEl = inp;
        }
      });

      if (!changedEl && force) {
        changedEl =
          ctl.list.filter(function (inp) {
            return inp.checked;
          })[0] || ctl.list[0];
      }

      if (changedEl) {
        changedEl.dispatchEvent(new Event("change", { bubbles: true }));
        LOG("JSF checkbox/radio change fired on", jsfLabelOf(changedEl).trim());
      } else {
        LOG("JSF category already in sync");
      }
      return true;
    }

    function applyJsfSearch(term) {
      var jsfInput = jsfSearchInput();
      if (!jsfInput) {
        if (!gridSearchReady()) {
          return false;
        }
        LOG("grid search via JSF request", term);
        rnGridTerm = term;
        shopGroups().forEach(function (g) {
          rnGridIds[g.queryId] = true;
          // doAjax() skips the request when the filter query is unchanged;
          // only the term changed, so force it (JSF does the same internally).
          // A new term starts again from page 1.
          if (typeof g.resetFiltersByName === "function") {
            g.resetFiltersByName("pagination");
          }
          g.currentQuery = { rn_force: Date.now() };
          g.doAjax();
        });
        return true;
      }
      LOG("applyJsfSearch", term);
      jsfInput.value = term;
      jsfInput.dispatchEvent(new Event("input", { bubbles: true }));
      jsfInput.dispatchEvent(new Event("change", { bubbles: true }));
      jsfInput.dispatchEvent(
        new KeyboardEvent("keyup", {
          bubbles: true,
          key: "Enter",
          keyCode: 13,
        }),
      );
      var jsfSubmit = document.querySelector(".jet-search-filter__submit");
      if (jsfSubmit) {
        jsfSubmit.click();
      }
      return true;
    }

    // Category + search → live listing filter (no reload).
    function tryLiveFilter(term) {
      var grids = targetGrids();
      if (grids.length) {
        grids.forEach(function (grid) {
          searchTargetGrid(grid, term);
        });
        closePreview();
        return true;
      }
      if (liveFilter !== "1" || typeof window.JetSmartFilters === "undefined") {
        return false;
      }
      if (!jsfSearchInput() && !gridSearchReady()) {
        return false;
      }
      // Only touch JSF's category when the visitor changed the HEADER
      // category; otherwise typing a search would wipe the sidebar's own
      // ticks (e.g. Batteries + Brakes + Engine Parts → Batteries only).
      var catChanged = catDirty;
      // Before any JSF request: JSF "Self" requests go to the page URL.
      syncPageState(term, catChanged);
      if (catDirty) {
        driveJsfCategory();
        catDirty = false;
      }
      applyJsfSearch(term);
      closePreview();
      return true;
    }

    /**
     * Same-page live search changed the term / category: keep the page in
     * step, so it doesn't keep showing the search it was opened with.
     *  - address bar: ?s= and ?product_cat= (history.replaceState, no reload)
     *  - JSF "Self" request URL (JetSmartFilterSettings.referrer_url)
     *  - "Search Results For: …" heading + browser tab title
     *
     * @param {string}  term       New search term ('' = cleared).
     * @param {boolean} catChanged The header category was changed too.
     */
    function syncPageState(term, catChanged) {
      var cat = currentCategory();

      function patch(href) {
        var u = new URL(href, window.location.origin);
        if (term) {
          u.searchParams.set("s", term);
        } else {
          u.searchParams.delete("s");
        }
        if (catChanged || u.searchParams.has("product_cat")) {
          if (cat) {
            u.searchParams.set("product_cat", cat);
          } else {
            u.searchParams.delete("product_cat");
          }
        }
        return u;
      }

      // Only pages that were OPENED as a search (?s=…). A category archive or
      // the shop keeps its URL: adding ?s= there could switch the template
      // (e.g. to the search results page) on reload.
      if (!urlState.s) {
        return;
      }

      try {
        var next = patch(window.location.href);
        window.history.replaceState(
          window.history.state,
          "",
          next.pathname + next.search + next.hash,
        );
        var jsfSettings = window.JetSmartFilterSettings;
        if (jsfSettings && jsfSettings.referrer_url) {
          var ref = patch(jsfSettings.referrer_url);
          jsfSettings.referrer_url = ref.pathname + ref.search;
        }
        // JSF rebuilds the address bar after each request from the query
        // string it saw at page load (filterGroup.baseUrlParams).
        shopGroups().forEach(function (g) {
          if (typeof g.baseUrlParams === "string") {
            var b = patch("/" + g.baseUrlParams);
            g.baseUrlParams = b.search;
          }
        });
      } catch (err) {
        LOG("syncPageState url error", err);
      }

      updateSearchHeading(term);
    }

    // Headings that showed the page's original search term.
    var searchHeadings = null;
    var lastHeadingTerm = null;
    var originalTitle = document.title;

    function updateSearchHeading(term) {
      var original = urlState.s;
      if (!original) {
        return;
      }
      if (null === searchHeadings) {
        searchHeadings = [];
        var needle = original.toLowerCase();
        document
          .querySelectorAll(
            "h1, h2, h3, .elementor-heading-title, .page-title, .archive-title",
          )
          .forEach(function (el) {
            // Never product cards / listings / menus — only the page's own
            // "Search Results For: …" style heading.
            if (
              root.contains(el) ||
              el.children.length > 1 ||
              el.closest(
                ".jet-listing-grid, .jet-listing-grid__item, li.product, .products, nav, .jet-menu, .elementor-nav-menu, .jet-popup",
              )
            ) {
              return;
            }
            var text = el.textContent || "";
            if (
              text.toLowerCase().indexOf(needle) !== -1 &&
              /search|result/i.test(text)
            ) {
              searchHeadings.push({ el: el, text: text });
            }
          });
      }
      if (term === lastHeadingTerm) {
        return;
      }
      lastHeadingTerm = term;

      var re = new RegExp(
        original.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"),
        "i",
      );
      searchHeadings.forEach(function (h) {
        h.el.textContent = term
          ? h.text.replace(re, term)
          : RNSmartSearch.i18n.allProducts || "All Products";
      });
      document.title = term
        ? originalTitle.replace(re, term)
        : originalTitle.replace(
            /^[^\u2013|-]*/,
            (RNSmartSearch.i18n.allProducts || "All Products") + " ",
          );
    }

    // ---- Live preview on typing (or same-page live listing filter) ----
    if (input) {
      input.addEventListener("input", function () {
        var term = input.value.trim();
        window.clearTimeout(timer);

        // Typing hides "Recent searches" so the results are right under the
        // box; clearing the box shows them again.
        var showingHistory = false;
        if (histBox) {
          if (term) {
            histBox.hidden = true;
          } else if (document.activeElement === input && renderHistory()) {
            if (list) {
              list.innerHTML = "";
            }
            openPreview();
            showingHistory = true;
          }
        }

        if (canLiveFilter()) {
          if (!showingHistory) {
            closePreview();
          }
          root.classList.add("rn-search--loading");
          timer = window.setTimeout(function () {
            tryLiveFilter(term);
            window.setTimeout(function () {
              root.classList.remove("rn-search--loading");
            }, 700);
          }, DEBOUNCE);
          return;
        }

        if (!showPreview) {
          return;
        }
        if (term.length < 2) {
          if (!showingHistory) {
            closePreview();
          }
          return;
        }
        timer = window.setTimeout(function () {
          fetchPreview(term);
        }, DEBOUNCE);
      });

      input.addEventListener("focus", function () {
        var t = input.value.trim();
        if (t.length < 2) {
          // Recent searches only for an empty box.
          if (!t && renderHistory() && preview) {
            if (list) {
              list.innerHTML = "";
            }
            preview.hidden = false;
          }
          return;
        }
        if (histBox) {
          histBox.hidden = true;
        }
        if (!canLiveFilter() && showPreview && list && list.children.length) {
          openPreview();
        }
      });

      input.addEventListener("blur", function () {
        addHistory(input.value.trim());
      });
    }

    // ---- Category dropdown ----
    if (catTrigger && catPanel) {
      catTrigger.addEventListener("click", function (e) {
        e.preventDefault();
        if (catPanel.hidden) {
          openCat();
        } else {
          closeCat();
        }
      });

      Array.prototype.forEach.call(options, function (opt) {
        opt.addEventListener("click", function (e) {
          e.preventDefault();
          setCategory(opt);
          catDirty = true;
          closeCat();

          // Same page as the listing + JSF search filter → full live filter.
          if (canLiveFilter()) {
            closePreview();
            tryLiveFilter(input ? input.value.trim() : "");
            return;
          }

          // No JSF search filter, but a JSF category filter exists → drive just that.
          if (
            liveFilter === "1" &&
            typeof window.JetSmartFilters !== "undefined" &&
            findJsfCategory()
          ) {
            syncPageState(input ? input.value.trim() : "", true);
            driveJsfCategory(true);
            catDirty = false;
            closePreview();
            return;
          }

          // Otherwise re-run the preview if there is an active term.
          if (showPreview && input) {
            var term = input.value.trim();
            if (term.length >= 2) {
              fetchPreview(term);
            }
          }
        });
      });
    }

    // ---- Submit → live filter on the results page, else go to shop ----
    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var term = input ? input.value.trim() : "";
        addHistory(term);
        LOG("submit", { term: term, category: currentCategory() });
        if (tryLiveFilter(term)) {
          return;
        }
        window.location.href = buildShopUrl(term, currentCategory());
      });
    }

    // ---- Recent-search clicks ----
    if (preview) {
      preview.addEventListener("click", function (e) {
        var t = e.target;
        if (!t || !t.closest) {
          return;
        }
        if (t.closest(".rn-search__history-clear")) {
          e.preventDefault();
          saveHistory([]);
          if (histBox) {
            histBox.hidden = true;
          }
          if (!input || input.value.trim().length < 2) {
            closePreview();
          }
          return;
        }
        var rm = t.closest(".rn-search__hist-remove");
        if (rm) {
          e.preventDefault();
          var row = rm.closest(".rn-search__hist-item");
          if (row) {
            removeHistory(row.getAttribute("data-term"));
            if (!renderHistory()) {
              closePreview();
            }
          }
          return;
        }
        var hi = t.closest(".rn-search__hist-item");
        if (hi) {
          e.preventDefault();
          runHistoryTerm(hi.getAttribute("data-term"));
        }
      });
    }

    document.addEventListener("click", function (e) {
      if (!root.contains(e.target)) {
        closePreview();
        closeCat();
      }
    });

    root.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        closePreview();
        closeCat();
      }
    });

    // ------------------------------------------------------------------
    // Sync header UI with the page state
    // ------------------------------------------------------------------
    var urlState = getUrlState();
    LOG(
      "page state",
      urlState,
      "archive category:",
      RNSmartSearch.currentCat || "(none)",
    );

    if (input && urlState.s && !input.value) {
      input.value = urlState.s;
    }

    // UI only: show the searched / browsed category in the header dropdown
    // (if it exists there). This never touches JetSmartFilters.
    if (urlState.cat) {
      if (currentCategory() !== urlState.cat) {
        setCategoryBySlug(urlState.cat);
      }
    } else if (RNSmartSearch.currentCat && !currentCategory()) {
      setCategoryBySlug(RNSmartSearch.currentCat);
    }

    /**
     * Mirror the JSF category ticks in the header dropdown (UI only):
     * none → "All", one → that category, several → "N Categories".
     * The hidden value is left empty for several, so a later header search
     * never narrows JSF back to a single category.
     */
    function syncHeaderFromJsf() {
      var ctl = findJsfCategory();
      if (!ctl) {
        return;
      }
      var labels = [];
      if (ctl.type === "select") {
        if (ctl.el.value) {
          labels.push(ctl.el.options[ctl.el.selectedIndex].textContent);
        }
      } else {
        ctl.list.forEach(function (inp) {
          if (inp.checked) {
            labels.push(jsfLabelOf(inp));
          }
        });
      }

      // Nothing ticked on a category archive → the grid falls back to the
      // archive's own category, so show that instead of "All".
      if (
        !labels.length &&
        RNSmartSearch.currentCat &&
        setCategoryBySlug(RNSmartSearch.currentCat)
      ) {
        return;
      }

      var match = null;
      if (labels.length <= 1) {
        var wanted = labels.length ? norm(labels[0]) : "";
        for (var i = 0; i < options.length; i++) {
          var v = options[i].getAttribute("data-value") || "";
          if (wanted ? norm(options[i].textContent) === wanted : v === "") {
            match = options[i];
            break;
          }
        }
      }

      if (match) {
        setCategory(match);
        return;
      }
      if (labels.length > 1) {
        if (catValue) {
          catValue.value = "";
        }
        if (catLabel) {
          catLabel.textContent = labels.length + " Categories";
        }
        // Highlight + check every ticked category in the dropdown.
        var picked = labels.map(norm);
        Array.prototype.forEach.call(options, function (o) {
          o.classList.toggle(
            "is-active",
            picked.indexOf(norm(o.textContent)) !== -1,
          );
        });
        LOG("header shows JSF multi-category", labels);
      }
    }

    if (liveFilter === "1") {
      document.addEventListener("change", function (e) {
        var ctl = findJsfCategory();
        if (!ctl) {
          return;
        }
        var hit =
          ctl.type === "select"
            ? e.target === ctl.el
            : ctl.list.indexOf(e.target) !== -1;
        if (hit) {
          syncHeaderFromJsf();
        }
      });
      if (urlState.hasJsf) {
        syncHeaderFromJsf();
      }
    }

    function logJsfState() {
      try {
        var jsf = window.JetSmartFilters;
        LOG(
          "JSF present:",
          !!jsf,
          "filterGroups:",
          jsf && jsf.filterGroups ? Object.keys(jsf.filterGroups) : "(n/a)",
        );
        var ctl = findJsfCategory();
        LOG(
          "JSF category control:",
          ctl ? ctl.type : "NOT FOUND",
          ctl && ctl.list ? ctl.list.length + " inputs" : "",
        );
        LOG("JSF search input:", !!jsfSearchInput());
        var grids = document.querySelectorAll(".jet-listing-grid");
        LOG(
          "listing grids on page:",
          grids.length,
          Array.prototype.map.call(grids, function (g) {
            return g.id || g.parentNode.id || "(no id)";
          }),
        );
      } catch (e) {
        LOG("logJsfState error", e);
      }
    }

    // Search-results page (?s=...&product_cat=...): push that state into
    // JetSmartFilters once it has initialised. Skipped when the URL already
    // carries ?jsf=... (JSF restores its own state) and on plain archives.
    function syncJsfFromUrl(tries) {
      if (!urlState.s && !urlState.cat) {
        return;
      }
      if (liveFilter !== "1") {
        return;
      }
      if (urlState.hasJsf) {
        LOG(
          "URL already has ?jsf=..., JetSmartFilters restores its own state, sync skipped",
        );
        return;
      }

      var jsf = window.JetSmartFilters;
      var hasControl = jsf && (jsfSearchInput() || findJsfCategory());
      var groupsReady =
        jsf && jsf.filterGroups && Object.keys(jsf.filterGroups).length > 0;

      if (hasControl && (groupsReady || tries < 18)) {
        LOG("JSF ready, syncing search state", urlState);
        logJsfState();
        if (urlState.cat) {
          driveJsfCategory(true);
        }
        if (urlState.s) {
          applyJsfSearch(urlState.s);
        }
        return;
      }
      if (tries > 0) {
        window.setTimeout(function () {
          syncJsfFromUrl(tries - 1);
        }, 300);
      } else {
        LOG("JSF not found on this page, sync skipped");
        logJsfState();
      }
    }
    syncJsfFromUrl(25);
  }

  function initAll(context) {
    var scope = context || document;
    var nodes = scope.querySelectorAll(".rn-search");
    Array.prototype.forEach.call(nodes, initInstance);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initAll();
    });
  } else {
    initAll();
  }

  // Elementor editor: re-init a widget after it re-renders. Works whether
  // Elementor initialised before or after this (possibly deferred) file.
  function bindElementorHook() {
    function add() {
      if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction(
          "frontend/element_ready/rn_smart_search.default",
          function ($scope) {
            initAll($scope[0]);
          },
        );
        return true;
      }
      return false;
    }
    if (!add()) {
      window.jQuery(window).on("elementor/frontend/init", add);
    }
  }

  setupJQuery();
  if (!jqReady) {
    document.addEventListener("DOMContentLoaded", setupJQuery);
  }
})();
