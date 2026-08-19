const typeButtons = document.querySelectorAll('.type-ctl [data-type]');
const applyType = (size) => {
    const next = ['md', 'lg', 'xl'].includes(size) ? size : 'md';
    document.documentElement.dataset.type = next;
    localStorage.setItem('rodante-scale', next);
    typeButtons.forEach((button) => {
        button.classList.toggle('is-on', button.dataset.type === next);
        button.setAttribute('aria-pressed', button.dataset.type === next ? 'true' : 'false');
    });
};
applyType(
    document.documentElement.dataset.type
        || localStorage.getItem('rodante-scale')
        || localStorage.getItem('rodanta-scale')
        || localStorage.getItem('tn-scale')
        || 'md'
);
typeButtons.forEach((button) => {
    button.addEventListener('click', () => applyType(button.dataset.type));
});

const shell = document.getElementById('appShell');
const openBtn = document.getElementById('navOpen');
const closeBtn = document.getElementById('navClose');
const backdrop = document.getElementById('navBackdrop');
const sidebar = document.getElementById('sidebar');

const setNav = (open) => {
    const wasOpen = shell?.classList.contains('is-nav');
    shell?.classList.toggle('is-nav', open);
    openBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('nav-lock', open);
    if (open) {
        closeBtn?.focus();
    } else if (wasOpen) {
        openBtn?.focus();
    }
};

openBtn?.addEventListener('click', () => setNav(true));
closeBtn?.addEventListener('click', () => setNav(false));
backdrop?.addEventListener('click', () => setNav(false));
document.querySelectorAll('.sb-link').forEach((link) => {
    link.addEventListener('click', () => setNav(false));
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && shell?.classList.contains('is-nav')) {
        setNav(false);
    }
});

sidebar?.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || !shell?.classList.contains('is-nav')) {
        return;
    }
    const focusable = sidebar.querySelectorAll('a, button, input, select, textarea');
    if (!focusable.length) {
        return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

const typeSelect = document.getElementById('unitType');
const cfgSelect = document.getElementById('unitConfig');
const odometerField = document.getElementById('odometerField');
const trailerSpecs = document.getElementById('trailerSpecs');
const configHint = document.getElementById('configHint');

const filterUnitConfigs = () => {
    if (!typeSelect || !cfgSelect) {
        return;
    }
    const selected = typeSelect.selectedOptions[0];
    const code = selected?.dataset.code ?? '';
    const powered = selected?.dataset.powered === '1';
    odometerField?.toggleAttribute('hidden', !powered);
    odometerField?.querySelector('input')?.toggleAttribute('disabled', !powered);
    trailerSpecs?.toggleAttribute('hidden', powered);
    trailerSpecs?.querySelectorAll('input, select').forEach((field) => {
        field.disabled = powered;
    });

    [...cfgSelect.options].forEach((option) => {
        if (!option.value) {
            return;
        }
        const types = (option.dataset.types || '').split(',').filter(Boolean);
        option.hidden = code !== '' && !types.includes(code);
        option.disabled = option.hidden;
    });

    if (cfgSelect.selectedOptions[0]?.hidden) {
        const preferred = !powered
            ? [...cfgSelect.options].find((option) => option.value && !option.hidden && option.textContent.startsWith('3E-S'))
            : null;
        const first = preferred || [...cfgSelect.options].find((option) => option.value && !option.hidden);
        if (first) {
            cfgSelect.value = first.value;
        }
    }

    if (configHint) {
        configHint.textContent = cfgSelect.selectedOptions[0]?.title ?? '';
    }
};

typeSelect?.addEventListener('change', filterUnitConfigs);
cfgSelect?.addEventListener('change', () => {
    if (configHint) {
        configHint.textContent = cfgSelect.selectedOptions[0]?.title ?? '';
    }
});
filterUnitConfigs();

const fillSelect = (select, items, placeholder) => {
    select.innerHTML = '';
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = placeholder;
    select.append(empty);
    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item.label || item.code || item.name || String(item.id);
        select.append(option);
    });
};

const bindTireCatalog = () => {
    const catalogEl = document.getElementById('tireCatalog');
    if (!catalogEl) {
        return;
    }
    let catalog = { brands: [], sizes: [] };
    try {
        catalog = JSON.parse(catalogEl.textContent || '{}');
    } catch {
        return;
    }
    const brands = catalog.brands || [];
    const sizeLabel = Object.fromEntries((catalog.sizes || []).map((size) => [String(size.id), size.label]));

    document.querySelectorAll('[data-catalog-row]').forEach((row) => {
        const brandSelect = row.querySelector('[data-catalog="brand"]');
        const modelSelect = row.querySelector('[data-catalog="model"]');
        const sizeSelect = row.querySelector('[data-catalog="size"]');
        if (!brandSelect || !modelSelect || !sizeSelect) {
            return;
        }

        const refresh = () => {
            const brand = brands.find((item) => String(item.id) === brandSelect.value);
            const models = brand?.models || [];
            const keepModel = modelSelect.dataset.selected || modelSelect.value;
            fillSelect(modelSelect, models, modelSelect.dataset.empty || 'Modelo');
            if (keepModel && models.some((item) => String(item.id) === String(keepModel))) {
                modelSelect.value = String(keepModel);
            }
            modelSelect.dataset.selected = '';

            const model = models.find((item) => String(item.id) === modelSelect.value);
            const sizes = (model?.size_ids || []).map((id) => ({ id, label: sizeLabel[String(id)] || String(id) }));
            const keepSize = sizeSelect.dataset.selected || sizeSelect.value;
            fillSelect(sizeSelect, sizes, sizeSelect.dataset.empty || 'Medida');
            if (keepSize && sizes.some((item) => String(item.id) === String(keepSize))) {
                sizeSelect.value = String(keepSize);
            }
            sizeSelect.dataset.selected = '';
        };

        brandSelect.addEventListener('change', () => {
            modelSelect.value = '';
            sizeSelect.value = '';
            refresh();
        });
        modelSelect.addEventListener('change', refresh);
        refresh();
    });
};
bindTireCatalog();

const recambio = () => {
    const mapEl = document.getElementById('slotMap');
    const dock = document.getElementById('recambioDock');
    if (!mapEl || !dock) {
        return;
    }

    let slots = [];
    try {
        slots = JSON.parse(mapEl.textContent || '[]');
    } catch {
        return;
    }

    const byId = Object.fromEntries(slots.map((slot) => [String(slot.id), slot]));
    const stockUrl = document.getElementById('stockPool')?.dataset.url || document.getElementById('stockSearch')?.dataset.url;
    const loadSlotStock = async (slot) => {
        if (!stockUrl) {
            return slot.stock || [];
        }
        const url = new URL(stockUrl, window.location.origin);
        url.searchParams.set('position_id', String(slot.id));
        const q = document.getElementById('stockSearch')?.value?.trim();
        if (q) {
            url.searchParams.set('q', q);
        }
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const stock = json.data || [];
            slot.stock = stock;
            return stock;
        } catch {
            return slot.stock || [];
        }
    };
    const applyStockDrop = (target, tireId) => {
        selectSlot(target.id);
        window.setTimeout(() => {
            if (target.empty) {
                installTire.value = String(tireId);
            } else {
                showAction('cambio');
                cambioTire.value = String(tireId);
            }
        }, 400);
    };
    const odometer = document.getElementById('sheetOdometer');
    const idle = document.getElementById('recambioIdle');
    const panel = document.getElementById('recambioPanel');
    const installForm = document.getElementById('recambioInstall');
    const mounted = document.getElementById('recambioMounted');
    const installTire = document.getElementById('installTire');
    const installEmpty = document.getElementById('installEmpty');
    const installSubmit = document.getElementById('installSubmit');
    const cambioTire = document.getElementById('cambioTire');
    const cambioEmpty = document.getElementById('cambioEmpty');
    const cambioSubmit = document.getElementById('cambioSubmit');
    const rotatePosition = document.getElementById('rotatePosition');
    const rotateEmpty = document.getElementById('rotateEmpty');
    const rotateSubmit = document.getElementById('rotateSubmit');
    const patronForm = document.getElementById('formPatron');
    let patterns = [];
    try {
        patterns = JSON.parse(document.getElementById('rotationPatterns')?.textContent || '[]');
    } catch {
        patterns = [];
    }
    const forms = {
        cambio: document.getElementById('formCambio'),
        pinchadura: document.getElementById('formPinchadura'),
        rotacion: document.getElementById('formRotacion'),
        retirar: document.getElementById('formRetirar'),
        incidencia: document.getElementById('formIncidencia'),
        medicion: document.getElementById('formMedicion'),
    };
    const measureFields = document.getElementById('measureFields');
    const measureEmpty = document.getElementById('measureEmpty');
    const measureSubmit = document.getElementById('measureSubmit');
    const svgNS = 'http://www.w3.org/2000/svg';
    let activePattern = null;

    const drawPatternArrows = (pattern) => {
        activePattern = pattern;
        const schematic = document.querySelector('.schematic--live');
        const svg = schematic?.querySelector('.schematic__arrows');
        if (!svg || !schematic) {
            return;
        }
        svg.replaceChildren();
        if (!pattern?.pairs?.length) {
            svg.setAttribute('hidden', '');
            return;
        }
        const root = schematic.getBoundingClientRect();
        svg.removeAttribute('hidden');
        svg.setAttribute('viewBox', `0 0 ${schematic.clientWidth} ${schematic.clientHeight}`);
        const defs = document.createElementNS(svgNS, 'defs');
        const marker = document.createElementNS(svgNS, 'marker');
        marker.setAttribute('id', 'rotArrow');
        marker.setAttribute('viewBox', '0 0 10 10');
        marker.setAttribute('refX', '8');
        marker.setAttribute('refY', '5');
        marker.setAttribute('markerWidth', '6');
        marker.setAttribute('markerHeight', '6');
        marker.setAttribute('orient', 'auto-start-reverse');
        const tip = document.createElementNS(svgNS, 'path');
        tip.setAttribute('d', 'M 0 0 L 10 5 L 0 10 z');
        tip.setAttribute('fill', '#e11d48');
        marker.append(tip);
        defs.append(marker);
        svg.append(defs);

        pattern.pairs.forEach(([from, to]) => {
            const a = schematic.querySelector(`[data-slot="${from}"]`);
            const b = schematic.querySelector(`[data-slot="${to}"]`);
            if (!a || !b) {
                return;
            }
            const aBox = a.getBoundingClientRect();
            const bBox = b.getBoundingClientRect();
            const line = document.createElementNS(svgNS, 'line');
            line.setAttribute('x1', String(aBox.left + aBox.width / 2 - root.left));
            line.setAttribute('y1', String(aBox.top + aBox.height / 2 - root.top));
            line.setAttribute('x2', String(bBox.left + bBox.width / 2 - root.left));
            line.setAttribute('y2', String(bBox.top + bBox.height / 2 - root.top));
            line.setAttribute('stroke', '#e11d48');
            line.setAttribute('stroke-width', '2');
            line.setAttribute('marker-start', 'url(#rotArrow)');
            line.setAttribute('marker-end', 'url(#rotArrow)');
            svg.append(line);
        });
    };

    window.addEventListener('resize', () => {
        if (activePattern) {
            drawPatternArrows(activePattern);
        }
    });

    const fillMeasureZones = (zones) => {
        measureFields.innerHTML = '';
        (zones || []).forEach((zone, i) => {
            const label = document.createElement('label');
            label.className = 'field';
            const span = document.createElement('span');
            span.textContent = `${zone.name} (mm)`;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `readings[${i}][zone_id]`;
            hidden.value = String(zone.id);
            const input = document.createElement('input');
            input.name = `readings[${i}][millimeters]`;
            input.type = 'number';
            input.step = '0.1';
            input.min = '0';
            input.required = true;
            input.className = 'inp';
            label.append(span, hidden, input);
            measureFields.append(label);
        });
        const hasZones = (zones || []).length > 0;
        measureEmpty.hidden = hasZones;
        measureSubmit.hidden = !hasZones;
    };

    const kmFmt = new Intl.NumberFormat('es-AR');

    const fillFacts = (tire) => {
        const list = document.getElementById('tireFactsList');
        list.innerHTML = '';
        const rows = [
            ['Marca', tire.brand],
            ['Modelo', [tire.code, tire.modelName].filter(Boolean).join(' · ')],
            ['Aplicación', tire.application],
            ['Medida', tire.size],
            ['Condición', tire.condition],
            ['Estado', tire.status],
            ['Vida', tire.life ? `Vida ${tire.life}` : null],
            ['Km acumulados', `${kmFmt.format(tire.km || 0)} km`],
            ['Profundidad', tire.tread || 'Sin medición'],
            ['Montada el', tire.mountedAt],
        ];
        rows.forEach(([label, value]) => {
            const row = document.createElement('div');
            const span = document.createElement('span');
            span.textContent = label;
            row.append(span, document.createTextNode(value || '—'));
            list.append(row);
        });
    };

    const syncOdometer = (form) => {
        const hidden = form.querySelector('input[name="odometer"]');
        if (hidden && odometer) {
            hidden.value = odometer.value;
        }
    };

    [installForm, patronForm, ...Object.values(forms)]
        .filter(Boolean)
        .forEach((form) => {
            form.addEventListener('submit', () => syncOdometer(form));
        });

    const showAction = (name) => {
        Object.entries(forms).forEach(([key, form]) => {
            if (form) {
                form.hidden = key !== name;
            }
        });
        dock.querySelectorAll('.slot-actions__btn').forEach((btn) => {
            btn.classList.toggle('is-on', btn.dataset.action === name);
        });
    };

    dock.querySelectorAll('.slot-actions__btn').forEach((btn) => {
        btn.addEventListener('click', () => showAction(btn.dataset.action));
    });

    const selectSlot = (id) => {
        const slot = byId[String(id)];
        if (!slot) {
            return;
        }

        document.querySelectorAll('.tire-box--action.is-selected').forEach((box) => box.classList.remove('is-selected'));
        document.querySelector(`.tire-box--action[data-slot="${id}"]`)?.classList.add('is-selected');

        idle.hidden = true;
        panel.hidden = false;
        if (patronForm) {
            patronForm.hidden = true;
        }
        document.querySelectorAll('.pattern-btn').forEach((btn) => btn.classList.remove('is-on'));
        drawPatternArrows(null);
        document.getElementById('recambioSlot').textContent = slot.code;
        document.getElementById('recambioRole').textContent = `${slot.name} · ${slot.role}`;
        const mountedName = document.getElementById('mountedName');

        if (slot.empty) {
            installForm.hidden = false;
            mounted.hidden = true;
            document.getElementById('installPosition').value = slot.id;
            loadSlotStock(slot).then((stock) => {
                fillSelect(installTire, stock, 'Elegir cubierta');
                const hasStock = stock.length > 0;
                installEmpty.hidden = hasStock;
                installTire.hidden = !hasStock;
                installSubmit.hidden = !hasStock;
                installTire.required = hasStock;
            });
            return;
        }

        installForm.hidden = true;
        mounted.hidden = false;
        mountedName.textContent = slot.tire.name;
        document.getElementById('mountedLink').href = slot.tire.url;
        fillFacts(slot.tire);
        document.getElementById('cambioPosition').value = slot.id;
        document.getElementById('pinchaduraPosition').value = slot.id;
        document.getElementById('rotacionFrom').value = slot.id;
        document.getElementById('retirarPosition').value = slot.id;
        document.getElementById('incidenciaPosition').value = slot.id;
        document.getElementById('medicionPosition').value = slot.id;
        fillMeasureZones(slot.tire.zones);
        loadSlotStock(slot).then((stock) => {
            fillSelect(cambioTire, stock, 'Elegir cubierta nueva');
            const hasStock = stock.length > 0;
            cambioEmpty.hidden = hasStock;
            cambioTire.hidden = !hasStock;
            cambioSubmit.hidden = !hasStock;
            cambioTire.required = hasStock;
        });
        fillSelect(rotatePosition, slot.rotateTo, 'Ubicación libre');
        const canRotate = slot.rotateTo.length > 0;
        rotateEmpty.hidden = canRotate;
        rotatePosition.hidden = !canRotate;
        rotateSubmit.hidden = !canRotate;
        rotatePosition.required = canRotate;
        showAction('cambio');
    };

    const clearDrop = () => {
        document.querySelectorAll('.is-drop').forEach((el) => el.classList.remove('is-drop'));
    };

    const targetsFor = (payload) => {
        if (payload.kind === 'stock') {
            if (payload.positions) {
                return slots.filter((slot) => payload.positions.includes(slot.id) || payload.positions.includes(Number(slot.id)));
            }
            return slots.filter((slot) => slot.stock.some((item) => String(item.id) === String(payload.tireId)));
        }
        const origin = byId[String(payload.slotId)];
        if (!origin?.tire) {
            return [];
        }
        return (origin.rotateTo || []).map((item) => byId[String(item.id)]).filter(Boolean);
    };

    const markTargets = (payload) => {
        clearDrop();
        targetsFor(payload).forEach((slot) => {
            document.querySelector(`.tire-box--action[data-slot="${slot.id}"]`)?.classList.add('is-drop');
            if (slot.id === Number(document.getElementById('refaccionDrop')?.dataset.spareSlot)) {
                document.getElementById('refaccionDrop')?.classList.add('is-drop');
            }
        });
    };

    const handleDrop = (targetSlotId, payload) => {
        const target = byId[String(targetSlotId)];
        if (!target) {
            return;
        }
        if (payload.kind === 'stock') {
            const allowed = target.stock.some((item) => String(item.id) === String(payload.tireId))
                || (payload.positions && payload.positions.map(String).includes(String(target.id)));
            if (!allowed && !payload.positions) {
                loadSlotStock(target).then((stock) => {
                    const ok = stock.some((item) => String(item.id) === String(payload.tireId));
                    if (!ok) {
                        return;
                    }
                    applyStockDrop(target, payload.tireId);
                });
                return;
            }
            if (!allowed) {
                return;
            }
            applyStockDrop(target, payload.tireId);
            return;
        }
        if (String(payload.slotId) === String(target.id)) {
            return;
        }
        const origin = byId[String(payload.slotId)];
        const canGo = (origin?.rotateTo || []).some((item) => String(item.id) === String(target.id));
        if (!origin?.tire || !canGo) {
            return;
        }
        selectSlot(origin.id);
        showAction('rotacion');
        rotatePosition.value = String(target.id);
    };

    const coarse = window.matchMedia('(pointer: coarse)').matches;
    let pickedStockId = null;
    let pickedPositions = null;
    const bindDrag = (el, payload) => {
        el.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('application/json', JSON.stringify(payload));
            event.dataTransfer.effectAllowed = 'move';
            el.classList.add('is-dragging');
            markTargets(payload);
        });
        el.addEventListener('dragend', () => {
            el.classList.remove('is-dragging');
            clearDrop();
        });
    };
    const bindChip = (chip) => {
        if (coarse) {
            chip.removeAttribute('draggable');
        }
        chip.addEventListener('click', async () => {
            pickedStockId = chip.dataset.tireId;
            document.querySelectorAll('.stock-chip[data-tire-id]').forEach((other) => other.classList.toggle('is-on', other === chip));
            pickedPositions = null;
            if (stockUrl) {
                const url = new URL(stockUrl, window.location.origin);
                url.searchParams.set('tire_id', pickedStockId);
                try {
                    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const json = await res.json();
                    pickedPositions = json.positions || [];
                } catch {
                    pickedPositions = [];
                }
            }
            markTargets({ kind: 'stock', tireId: pickedStockId, positions: pickedPositions });
        });
        if (!coarse) {
            bindDrag(chip, { kind: 'stock', tireId: chip.dataset.tireId });
        }
    };
    document.querySelectorAll('.stock-chip[data-tire-id]').forEach(bindChip);

    const stockSearch = document.getElementById('stockSearch');
    let searchTimer = null;
    const renderStockChips = (items) => {
        const pool = document.getElementById('stockPool');
        if (!pool) {
            return;
        }
        pool.querySelectorAll('.stock-chip').forEach((el) => el.remove());
        const empty = pool.querySelector('.stock-rail__empty');
        if (empty) {
            empty.hidden = items.length > 0;
        }
        items.forEach((item) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'stock-chip';
            chip.dataset.tireId = String(item.id);
            chip.innerHTML = `<strong>${item.name || item.label}</strong><span>${item.meta || ''}</span>`;
            pool.append(chip);
            bindChip(chip);
        });
    };
    stockSearch?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(async () => {
            if (!stockUrl) {
                return;
            }
            const url = new URL(stockUrl, window.location.origin);
            if (stockSearch.value.trim()) {
                url.searchParams.set('q', stockSearch.value.trim());
            }
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            renderStockChips(json.data || []);
        }, 250);
    });

    document.querySelectorAll('.tire-box--action[data-slot]').forEach((box) => {
        box.addEventListener('click', () => {
            if (pickedStockId) {
                handleDrop(box.dataset.slot, { kind: 'stock', tireId: pickedStockId });
                pickedStockId = null;
                chips.forEach((chip) => chip.classList.remove('is-on'));
                clearDrop();
                return;
            }
            selectSlot(box.dataset.slot);
        });
    });

    if (!coarse) {
        document.querySelectorAll('.tire-box--action[data-tire-id]').forEach((box) => {
            bindDrag(box, { kind: 'slot', slotId: box.dataset.slot, tireId: box.dataset.tireId });
        });
    }

    const droppables = [
        ...document.querySelectorAll('.tire-box--action[data-slot]'),
        document.getElementById('refaccionDrop'),
    ].filter(Boolean);

    droppables.forEach((el) => {
        el.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        });
        el.addEventListener('drop', (event) => {
            event.preventDefault();
            clearDrop();
            let payload = null;
            try {
                payload = JSON.parse(event.dataTransfer.getData('application/json') || '{}');
            } catch {
                return;
            }
            handleDrop(el.dataset.slot || el.dataset.spareSlot, payload);
        });
    });

    const menu = document.getElementById('slotMenu');
    let menuSlotId = null;
    const hideMenu = () => {
        if (menu) {
            menu.hidden = true;
        }
        menuSlotId = null;
    };

    document.querySelectorAll('.tire-box--action[data-slot]').forEach((box) => {
        box.addEventListener('contextmenu', (event) => {
            const slot = byId[String(box.dataset.slot)];
            if (!slot || slot.empty || !menu) {
                return;
            }
            event.preventDefault();
            menuSlotId = slot.id;
            menu.hidden = false;
            menu.style.left = `${event.clientX}px`;
            menu.style.top = `${event.clientY}px`;
        });
    });

    menu?.querySelectorAll('[data-menu]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const slot = byId[String(menuSlotId)];
            hideMenu();
            if (!slot) {
                return;
            }
            if (btn.dataset.menu === 'ficha' && slot.tire?.url) {
                window.location.href = slot.tire.url;
                return;
            }
            selectSlot(slot.id);
            const action = { quitar: 'retirar', rotar: 'rotacion', cambio: 'cambio', incidencia: 'incidencia', medicion: 'medicion' }[btn.dataset.menu];
            if (action) {
                showAction(action);
            }
        });
    });

    document.addEventListener('click', hideMenu);

    document.querySelectorAll('.pattern-btn[data-pattern]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const pattern = patterns.find((item) => item.code === btn.dataset.pattern);
            if (!pattern || !pattern.ready || !patronForm) {
                return;
            }
            idle.hidden = true;
            panel.hidden = true;
            patronForm.hidden = false;
            document.getElementById('patronCode').value = pattern.code;
            document.getElementById('patronHint').textContent = pattern.hint;
            document.querySelectorAll('.pattern-btn').forEach((other) => {
                other.classList.toggle('is-on', other === btn);
            });
            drawPatternArrows(pattern);
        });
    });
};

recambio();

const bindSearchSuggest = () => {
    const form = document.querySelector('.top-search[data-suggest]');
    const input = document.getElementById('topSearch');
    const box = document.getElementById('searchSuggest');
    if (!form || !input || !box) {
        return;
    }
    const url = form.dataset.suggest;
    let timer = 0;
    let seq = 0;
    let active = -1;
    let links = [];

    const hide = () => {
        box.hidden = true;
        box.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        active = -1;
        links = [];
    };

    const paint = (items) => {
        box.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'search-suggest__empty';
            empty.textContent = 'No hay coincidencias. Enter busca en el listado.';
            box.append(empty);
            box.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            links = [];
            return;
        }
        items.forEach((item, index) => {
            const a = document.createElement('a');
            a.href = item.url;
            a.id = `searchOpt${index}`;
            a.setAttribute('role', 'option');
            a.innerHTML = `<span class="search-suggest__type">${item.type_label}</span><strong>${item.label}</strong><span class="search-suggest__hint">${item.hint || ''}</span>`;
            box.append(a);
        });
        links = [...box.querySelectorAll('a')];
        box.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        active = -1;
    };

    const lookup = () => {
        const term = input.value.trim();
        if (term.length < 2) {
            hide();
            return;
        }
        const n = ++seq;
        fetch(`${url}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } })
            .then((res) => res.json())
            .then((data) => {
                if (n !== seq) {
                    return;
                }
                paint(data.items || []);
            })
            .catch(() => {
                if (n === seq) {
                    hide();
                }
            });
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(lookup, 180);
    });
    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2) {
            lookup();
        }
    });
    input.addEventListener('keydown', (event) => {
        if (box.hidden || !links.length) {
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            active = (active + 1) % links.length;
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active - 1 + links.length) % links.length;
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            links[active].click();
            return;
        } else if (event.key === 'Escape') {
            hide();
            return;
        } else {
            return;
        }
        links.forEach((link, index) => link.classList.toggle('is-on', index === active));
        links[active]?.scrollIntoView({ block: 'nearest' });
    });
    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) {
            hide();
        }
    });
};
bindSearchSuggest();

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if ((form.method || 'get').toLowerCase() === 'get') {
        return;
    }
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
        return;
    }
    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    const button = form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
    if (button && !button.disabled) {
        button.disabled = true;
        if (button.tagName === 'BUTTON' && !button.dataset.label) {
            button.dataset.label = button.textContent;
            button.textContent = 'Guardando…';
        }
    }
});

(window.__rodanteFieldErrors || []).forEach((name) => {
    document.querySelectorAll(`[name="${name}"]`).forEach((el) => {
        el.setAttribute('aria-invalid', 'true');
    });
});

document.querySelectorAll('.flash.toast').forEach((el) => {
    window.setTimeout(() => {
        el.classList.add('is-out');
        window.setTimeout(() => el.remove(), 350);
    }, 6500);
});

document.querySelectorAll('.table-responsive table').forEach((table) => {
    const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    table.querySelectorAll('tbody tr').forEach((tr) => {
        [...tr.children].forEach((td, i) => {
            if (headers[i]) {
                td.setAttribute('data-label', headers[i]);
            }
        });
    });
});
