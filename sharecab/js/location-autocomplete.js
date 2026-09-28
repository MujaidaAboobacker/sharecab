/**
 * ShareCab - Real Location Autocomplete & Geocoding Component
 * Powered by OpenStreetMap (Photon & Nominatim API) + Transit Hubs Dataset
 */

(function () {
    // Pre-loaded regional dataset for instant offline/fast suggestions
    const POPULAR_LOCATIONS = [
        // Kerala & South India Transit Hubs & Landmarks
        { name: "Ernakulam South Railway Station", city: "Kochi, Kerala", lat: 9.9686, lon: 76.2905 },
        { name: "Ernakulam North (Town) Railway Station", city: "Kochi, Kerala", lat: 9.9922, lon: 76.2882 },
        { name: "Cochin International Airport (COK)", city: "Nedumbassery, Kochi", lat: 10.1518, lon: 76.3929 },
        { name: "Marine Drive Walkway", city: "Kochi, Kerala", lat: 9.9790, lon: 76.2760 },
        { name: "Fort Kochi Beach & Chinese Fishing Nets", city: "Kochi, Kerala", lat: 9.9676, lon: 76.2425 },
        { name: "Lulu International Shopping Mall", city: "Edappally, Kochi", lat: 10.0278, lon: 76.3081 },
        { name: "Infopark SmartCity Campus", city: "Kakkanad, Kochi", lat: 10.0159, lon: 76.3639 },
        { name: "Kaloor Jawaharlal Nehru Stadium", city: "Kaloor, Kochi", lat: 9.9981, lon: 76.3005 },
        { name: "Aluva KSRTC Bus Stand & Metro", city: "Aluva, Kerala", lat: 10.1076, lon: 76.3516 },
        { name: "Vyttila Mobility Hub", city: "Kochi, Kerala", lat: 9.9691, lon: 76.3197 },
        { name: "MG Road Metro Station", city: "Kochi, Kerala", lat: 9.9754, lon: 76.2831 },
        { name: "Thiruvananthapuram Central Railway Station", city: "Trivandrum, Kerala", lat: 8.4871, lon: 76.9529 },
        { name: "Technopark Campus (Phase 1-3)", city: "Trivandrum, Kerala", lat: 8.5581, lon: 76.8812 },
        { name: "Trivandrum International Airport (TRV)", city: "Trivandrum, Kerala", lat: 8.4821, lon: 76.9200 },
        { name: "Kozhikode Railway Station", city: "Calicut, Kerala", lat: 11.2483, lon: 75.7839 },
        { name: "Calicut International Airport (CCJ)", city: "Karipur, Malappuram", lat: 11.1367, lon: 75.9553 },
        
        // Major Metros & Cities
        { name: "Kempegowda International Airport (BLR)", city: "Bangalore, Karnataka", lat: 13.1986, lon: 77.7066 },
        { name: "Krantivira Sangolli Rayanna (Bengaluru City) Station", city: "Bangalore, Karnataka", lat: 12.9781, lon: 77.5695 },
        { name: "Electronic City Tech Hub", city: "Bangalore, Karnataka", lat: 12.8452, lon: 77.6602 },
        { name: "Whitefield IT Park", city: "Bangalore, Karnataka", lat: 12.9698, lon: 77.7500 },
        { name: "Chennai Central Railway Station (MAS)", city: "Chennai, Tamil Nadu", lat: 13.0827, lon: 80.2707 },
        { name: "Chennai International Airport (MAA)", city: "Chennai, Tamil Nadu", lat: 12.9941, lon: 80.1709 },
        { name: "Chhatrapati Shivaji Maharaj Terminus (CSMT)", city: "Mumbai, Maharashtra", lat: 18.9401, lon: 72.8352 },
        { name: "Chhatrapati Shivaji Maharaj Airport (BOM)", city: "Mumbai, Maharashtra", lat: 19.0896, lon: 72.8656 },
        { name: "New Delhi Railway Station (NDLS)", city: "Delhi, India", lat: 28.6415, lon: 77.2185 },
        { name: "Indira Gandhi International Airport (DEL)", city: "Delhi, India", lat: 28.5562, lon: 77.1000 },
        { name: "Rajiv Gandhi International Airport (HYD)", city: "Hyderabad, Telangana", lat: 17.2403, lon: 78.4294 },
        { name: "HITEC City Cyberabad", city: "Hyderabad, Telangana", lat: 17.4435, lon: 78.3772 }
    ];

    /**
     * Search local database for fast matching
     */
    function searchLocalLocations(query) {
        const q = query.toLowerCase().trim();
        return POPULAR_LOCATIONS.filter(item => 
            item.name.toLowerCase().includes(q) || 
            item.city.toLowerCase().includes(q)
        ).slice(0, 5);
    }

    /**
     * Query Photon OpenStreetMap geocoding API
     */
    async function searchOnlineLocations(query) {
        if (!query || query.length < 2) return [];
        try {
            const url = `https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=6&lang=en`;
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 2500);

            const res = await fetch(url, { signal: controller.signal });
            clearTimeout(timeoutId);

            if (!res.ok) throw new Error("Network error");
            const data = await res.json();

            if (data && data.features) {
                return data.features.map(f => {
                    const props = f.properties || {};
                    const cityParts = [props.city || props.town || props.district, props.state, props.country].filter(Boolean);
                    return {
                        name: props.name || query,
                        city: cityParts.join(", ") || "Location",
                        lat: f.geometry.coordinates[1],
                        lon: f.geometry.coordinates[0]
                    };
                });
            }
        } catch (e) {
            // Fallback to OSM Nominatim if Photon is unreachable
            try {
                const nomUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`;
                const nomRes = await fetch(nomUrl);
                const nomData = await nomRes.json();
                if (nomData && nomData.length > 0) {
                    return nomData.map(item => ({
                        name: item.display_name.split(",")[0],
                        city: item.display_name.split(",").slice(1, 4).join(","),
                        lat: parseFloat(item.lat),
                        lon: parseFloat(item.lon)
                    }));
                }
            } catch (nomErr) {
                // Return empty if completely offline
            }
        }
        return [];
    }

    /**
     * Attach autocomplete behavior to an input element
     */
    function setupAutocomplete(input, onSelectCallback) {
        if (!input || input.dataset.hasAutocomplete) return;
        input.dataset.hasAutocomplete = "true";

        // Wrap input in a relative container if not already wrapped
        let wrapper = input.parentElement;
        if (!wrapper.classList.contains("autocomplete-wrapper")) {
            const newWrapper = document.createElement("div");
            newWrapper.className = "autocomplete-wrapper";
            input.parentNode.insertBefore(newWrapper, input);
            newWrapper.appendChild(input);
            wrapper = newWrapper;
        }

        // Create dropdown container
        const dropdown = document.createElement("div");
        dropdown.className = "autocomplete-dropdown";
        dropdown.style.display = "none";
        wrapper.appendChild(dropdown);

        let debounceTimer = null;
        let selectedIndex = -1;
        let currentSuggestions = [];

        function renderSuggestions(items) {
            dropdown.innerHTML = "";
            currentSuggestions = items;
            selectedIndex = -1;

            if (items.length === 0) {
                dropdown.style.display = "none";
                return;
            }

            items.forEach((item, index) => {
                const itemEl = document.createElement("div");
                itemEl.className = "autocomplete-item";
                itemEl.innerHTML = `
                    <div class="autocomplete-icon">📍</div>
                    <div class="autocomplete-text">
                        <strong class="autocomplete-title">${escapeHTML(item.name)}</strong>
                        <small class="autocomplete-city">${escapeHTML(item.city)}</small>
                    </div>
                `;

                itemEl.addEventListener("mousedown", function (e) {
                    e.preventDefault(); // Prevent input blur before click
                    selectItem(item);
                });

                dropdown.appendChild(itemEl);
            });

            dropdown.style.display = "block";
        }

        function selectItem(item) {
            input.value = item.name + (item.city ? ` (${item.city.split(',')[0].trim()})` : '');
            input.dataset.lat = item.lat;
            input.dataset.lon = item.lon;
            input.dataset.verified = "true";
            dropdown.style.display = "none";

            // Trigger change event
            input.dispatchEvent(new Event("change", { bubbles: true }));

            if (typeof onSelectCallback === "function") {
                onSelectCallback(item);
            }
        }

        function handleInput() {
            const val = input.value.trim();
            // Mark unverified if user edits text
            input.dataset.verified = "false";

            if (val.length < 2) {
                dropdown.style.display = "none";
                return;
            }

            clearTimeout(debounceTimer);

            // Instant local results
            const localMatches = searchLocalLocations(val);
            if (localMatches.length > 0) {
                renderSuggestions(localMatches);
            }

            // Fetch live geocoding suggestions
            debounceTimer = setTimeout(async () => {
                const onlineMatches = await searchOnlineLocations(val);
                
                // Combine and deduplicate
                const combined = [...localMatches];
                const seen = new Set(localMatches.map(m => m.name.toLowerCase()));

                onlineMatches.forEach(o => {
                    if (!seen.has(o.name.toLowerCase())) {
                        combined.push(o);
                        seen.add(o.name.toLowerCase());
                    }
                });

                if (combined.length > 0) {
                    renderSuggestions(combined.slice(0, 7));
                } else if (localMatches.length === 0) {
                    dropdown.style.display = "none";
                }
            }, 250);
        }

        input.addEventListener("input", handleInput);
        input.addEventListener("focus", function () {
            if (input.value.trim().length >= 2) {
                handleInput();
            }
        });

        // Keyboard navigation
        input.addEventListener("keydown", function (e) {
            const items = dropdown.querySelectorAll(".autocomplete-item");
            if (dropdown.style.display === "none" || items.length === 0) return;

            if (e.key === "ArrowDown") {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateHighlight(items);
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                updateHighlight(items);
            } else if (e.key === "Enter") {
                if (selectedIndex >= 0 && selectedIndex < currentSuggestions.length) {
                    e.preventDefault();
                    selectItem(currentSuggestions[selectedIndex]);
                }
            } else if (e.key === "Escape") {
                dropdown.style.display = "none";
            }
        });

        function updateHighlight(items) {
            items.forEach((it, idx) => {
                if (idx === selectedIndex) {
                    it.classList.add("highlighted");
                    it.scrollIntoView({ block: "nearest" });
                } else {
                    it.classList.remove("highlighted");
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener("click", function (e) {
            if (!wrapper.contains(e.target)) {
                dropdown.style.display = "none";
            }
        });
    }

    function escapeHTML(str) {
        return (str || "").replace(/[&<>"']/g, function (m) {
            return {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;"
            }[m];
        });
    }

    /**
     * Asynchronously check if a given location query resolves to a real place
     */
    async function verifyRealLocation(locationText) {
        if (!locationText || locationText.trim().length < 2) return null;
        
        // 1. Check local dataset
        const local = searchLocalLocations(locationText);
        if (local.length > 0) return local[0];

        // 2. Query online
        const online = await searchOnlineLocations(locationText);
        if (online.length > 0) return online[0];

        return null;
    }

    // Expose globally
    window.ShareCabLocations = {
        setupAutocomplete: setupAutocomplete,
        verifyRealLocation: verifyRealLocation,
        searchLocalLocations: searchLocalLocations
    };

    // Auto-initialize inputs with data-autocomplete="location"
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll('input[data-autocomplete="location"]').forEach(input => {
            setupAutocomplete(input);
        });
    });
})();
