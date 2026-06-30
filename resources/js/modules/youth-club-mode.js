/**
 * ClubKit YouthClubMode – Familie-Tab Logic
 *
 * Erweitert das Member-Modal um den Familie-Tab.
 * Kommuniziert über das ckOn/ckEmit-System mit members-modal.js.
 *
 * Abhängigkeiten:
 *   - window.CK_Members       (Data Bridge: members mit family{}-Objekt pro Eintrag)
 *   - window.CK_YouthClubMode (Data Bridge: csrf, routes, allMembers, relations)
 *   - ckOn(), ckTabEnable()   aus resources/js/app.js
 *
 * Regel: Nur classList-Operationen – kein el.style.*
 *
 * Ladereihenfolge-Hinweis:
 *   app.js lädt als type="module" (deferred).
 *   Dieses Script läuft synchron am Body-Ende → ckOn ist noch nicht definiert.
 *   Deshalb: ckOn-Registrierung in DOMContentLoaded wrappen.
 */
(function () {
    'use strict';

    // ── Modul-State ────────────────────────────────────────────────────────
    let currentMemberId = null;

    // ── DOMContentLoaded: Event-Listener registrieren ─────────────────────
    document.addEventListener('DOMContentLoaded', function () {

        // Auf Modal-Öffnen lauschen
        ckOn('member.modal.open', function (detail) {
            currentMemberId = detail.memberId || null;

            if (detail.mode === 'create') {
                ckTabEnable('memberFamilyTabBtn', 'memberFamilyCreateHint', false);
                _clearAddForm();
                return;
            }

            // Edit-Modus: Tab aktivieren + Liste rendern
            ckTabEnable('memberFamilyTabBtn', 'memberFamilyCreateHint', true);
            _clearAddForm();
            _renderFamilyList();
        });

        // Beziehungs-Dropdown: Mitglieder-Dropdown filtern
        const relSelect = document.getElementById('mFieldRelationship');
        if (relSelect) {
            relSelect.addEventListener('change', _onRelationshipChange);
        }

        // Mitglieds-Dropdown: Hinzufügen-Button freischalten
        const memberSelect = document.getElementById('mFieldRelatedMember');
        if (memberSelect) {
            memberSelect.addEventListener('change', function () {
                const addBtn = document.getElementById('mBtnAddRelation');
                if (addBtn) addBtn.disabled = !memberSelect.value;
            });
        }

        // Hinzufügen-Button
        const addBtn = document.getElementById('mBtnAddRelation');
        if (addBtn) {
            addBtn.addEventListener('click', _onAddRelation);
        }

    }); // end DOMContentLoaded

    // ── Dropdown 1 geändert: Dropdown 2 befüllen ──────────────────────────

    function _onRelationshipChange() {
        const relSelect    = document.getElementById('mFieldRelationship');
        const memberSelect = document.getElementById('mFieldRelatedMember');
        const addBtn       = document.getElementById('mBtnAddRelation');
        if (!relSelect || !memberSelect) return;

        const relationship = relSelect.value;
        _hideAddError();

        if (!relationship) {
            memberSelect.disabled = true;
            memberSelect.innerHTML = '<option value="">– erst Beziehung wählen –</option>';
            if (addBtn) addBtn.disabled = true;
            return;
        }

        const filtered = _getFilteredMembers(relationship);

        memberSelect.innerHTML = '<option value="">– Mitglied wählen –</option>';
        filtered.forEach(function (m) {
            const opt = document.createElement('option');
            opt.value       = m.id;
            opt.textContent = m.name;
            memberSelect.appendChild(opt);
        });

        memberSelect.disabled = (filtered.length === 0);
        if (filtered.length === 0) {
            memberSelect.innerHTML = '<option value="">Keine passenden Mitglieder</option>';
        }
        if (addBtn) addBtn.disabled = true; // erst nach Mitglieds-Auswahl freischalten
    }

    // ── Filter-Logik ──────────────────────────────────────────────────────

    /**
     * Filtert allMembers nach den Regeln des gewählten Beziehungstyps.
     * @param  {string} relationshipType  'father'|'mother'|'father_of'|'mother_of'|'sibling'
     * @return {Array}  Gefilterte Mitglieder [{id, name, ...}]
     */
    function _getFilteredMembers(relationshipType) {
        const ycm       = window.CK_YouthClubMode || {};
        const allMem    = ycm.allMembers  || {};
        const relations = ycm.relations   || [];
        const all       = Object.values(allMem);

        // Immer: sich selbst ausschließen
        let result = all.filter(function (m) { return m.id !== currentMemberId; });

        switch (relationshipType) {

            case 'father':
                // Volljährig (oder kein Geburtsdatum) + männlich/divers/ohne Angabe
                // + ist noch nicht Vater des aktuellen Mitglieds
                result = result.filter(function (m) {
                    return _isAdultOrNoDob(m)
                        && _isGender(m, ['male', 'diverse', null])
                        && !_relationExists(m.id, currentMemberId, 'father', relations);
                });
                break;

            case 'mother':
                // Volljährig + weiblich/divers/ohne Angabe
                // + ist noch nicht Mutter des aktuellen Mitglieds
                result = result.filter(function (m) {
                    return _isAdultOrNoDob(m)
                        && _isGender(m, ['female', 'diverse', null])
                        && !_relationExists(m.id, currentMemberId, 'mother', relations);
                });
                break;

            case 'father_of':
                // Mitglieder, die noch KEINEN Vater haben
                result = result.filter(function (m) {
                    return !_hasParent(m.id, 'father', relations);
                });
                break;

            case 'mother_of':
                // Mitglieder, die noch KEINE Mutter haben
                result = result.filter(function (m) {
                    return !_hasParent(m.id, 'mother', relations);
                });
                break;

            case 'sibling':
                // Alle Mitglieder, die noch kein Geschwister des aktuellen sind
                result = result.filter(function (m) {
                    return !_areSiblings(currentMemberId, m.id, relations);
                });
                break;
        }

        return result.sort(function (a, b) {
            return a.name.localeCompare(b.name, 'de');
        });
    }

    // Ist m.age >= 18 (oder kein Geburtsdatum)?
    function _isAdultOrNoDob(m) {
        if (!m.date_of_birth) return true; // kein Datum → trotzdem anzeigen
        const dob   = new Date(m.date_of_birth);
        const ageMs = Date.now() - dob.getTime();
        return ageMs / (1000 * 60 * 60 * 24 * 365.25) >= 18;
    }

    // Hat m.gender einen der erlaubten Werte?
    function _isGender(m, allowed) {
        return allowed.indexOf(m.gender) !== -1;
    }

    // Existiert bereits: primary=parentId, secondary=childId, relationship=rel?
    function _relationExists(parentId, childId, rel, relations) {
        return relations.some(function (r) {
            return r.primary_member_id   === parentId
                && r.secondary_member_id === childId
                && r.relationship        === rel;
        });
    }

    // Hat memberId bereits einen Elternteil vom Typ rel?
    function _hasParent(memberId, rel, relations) {
        return relations.some(function (r) {
            return r.secondary_member_id === memberId && r.relationship === rel;
        });
    }

    // Sind id1 und id2 bereits Geschwister?
    function _areSiblings(id1, id2, relations) {
        return relations.some(function (r) {
            return r.relationship === 'sibling'
                && ((r.primary_member_id === id1 && r.secondary_member_id === id2)
                 || (r.primary_member_id === id2 && r.secondary_member_id === id1));
        });
    }

    // ── Verbindung hinzufügen (AJAX) ─────────────────────────────────────

    function _onAddRelation() {
        const relSelect    = document.getElementById('mFieldRelationship');
        const memberSelect = document.getElementById('mFieldRelatedMember');
        const addBtn       = document.getElementById('mBtnAddRelation');
        if (!relSelect || !memberSelect) return;

        const relationship    = relSelect.value;
        const relatedMemberId = memberSelect.value;
        if (!relationship || !relatedMemberId || !currentMemberId) return;

        _hideAddError();
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.classList.add('ck-btn--loading');
        }

        const ycm = window.CK_YouthClubMode || {};
        const url = ((ycm.routes || {}).relationsBase || '') + '/' + currentMemberId + '/relations';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     ycm.csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                relationship:      relationship,
                related_member_id: parseInt(relatedMemberId, 10),
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (addBtn) addBtn.classList.remove('ck-btn--loading');

            if (!data.success) {
                _showAddError(data.message || 'Fehler beim Speichern.');
                if (addBtn) addBtn.disabled = false;
                return;
            }

            // Neue Verbindung lokal hinzufügen
            (window.CK_YouthClubMode.relations || []).push(data.relation);

            // Formular zurücksetzen + Liste neu rendern
            _clearAddForm();
            _renderFamilyList();
        })
        .catch(function () {
            if (addBtn) {
                addBtn.classList.remove('ck-btn--loading');
                addBtn.disabled = false;
            }
            _showAddError('Netzwerkfehler. Bitte versuche es erneut.');
        });
    }

    // ── Verbindung entfernen (AJAX) ───────────────────────────────────────

    /**
     * Wird aus dem onclick im gerenderten HTML aufgerufen.
     * @param {number} relationId
     */
    window.ckYcmDeleteRelation = function (relationId) {
        if (!confirm('Verbindung wirklich entfernen?')) return;

        const ycm = window.CK_YouthClubMode || {};
        const url = ((ycm.routes || {}).relationsBase || '')
                + '/' + currentMemberId
                + '/relations/' + relationId;

        fetch(url, {
            method: 'DELETE',
            headers: {
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     ycm.csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) {
                alert(data.message || 'Fehler beim Löschen.');
                return;
            }

            // Verbindung lokal entfernen
            window.CK_YouthClubMode.relations = (ycm.relations || []).filter(function (r) {
                return r.id !== data.relation_id;
            });

            _renderFamilyList();
        })
        .catch(function () {
            alert('Netzwerkfehler. Bitte versuche es erneut.');
        });
    };

    // ── Familie-Liste rendern ─────────────────────────────────────────────

    function _renderFamilyList() {
        const list = document.getElementById('mFamilyList');
        if (!list || !currentMemberId) return;

        const ycm       = window.CK_YouthClubMode || {};
        const relations = ycm.relations  || [];
        const allMem    = ycm.allMembers || {};
        const family    = _buildFamily(currentMemberId, relations, allMem);

        const items = [];

        if (family.father) {
            items.push(_familyItemHtml('Vater', family.father.name, family.father.relation_id));
        }
        if (family.mother) {
            items.push(_familyItemHtml('Mutter', family.mother.name, family.mother.relation_id));
        }
        family.children.forEach(function (child) {
            const label = child.parent_relation === 'father' ? 'Kind (als Vater)' : 'Kind (als Mutter)';
            items.push(_familyItemHtml(label, child.name, child.relation_id));
        });
        family.siblings.forEach(function (sib) {
            items.push(_familyItemHtml('Geschwister', sib.name, sib.relation_id));
        });

        if (items.length === 0) {
            list.innerHTML = '<p class="ck-text-muted">Noch keine Verbindungen eingetragen.</p>';
        } else {
            list.innerHTML = items.join('');
        }
    }

    function _familyItemHtml(label, name, relationId) {
        return '<div class="ck-family-item">'
             + '<span class="ck-family-item__label">' + _esc(label) + '</span>'
             + '<span class="ck-family-item__name">'  + _esc(name)  + '</span>'
             + '<button type="button" class="ck-btn ck-btn--danger ck-btn--xs" '
             + 'onclick="ckYcmDeleteRelation(' + relationId + ')">×</button>'
             + '</div>';
    }

    /**
     * Berechnet die Familiendaten für ein Mitglied aus dem Relations-Array.
     * Spiegelt die Logik aus FamilyService::buildFamilyData().
     */
    function _buildFamily(memberId, relations, allMem) {
        const family = { father: null, mother: null, children: [], siblings: [] };

        relations.forEach(function (r) {
            const pid = r.primary_member_id;
            const sid = r.secondary_member_id;
            const rel = r.relationship;

            if (sid === memberId && rel === 'father') {
                family.father = { relation_id: r.id, id: pid, name: (allMem[pid] || {}).name || '?' };
            }
            if (sid === memberId && rel === 'mother') {
                family.mother = { relation_id: r.id, id: pid, name: (allMem[pid] || {}).name || '?' };
            }
            if (pid === memberId && (rel === 'father' || rel === 'mother')) {
                family.children.push({
                    relation_id:     r.id,
                    id:              sid,
                    name:            (allMem[sid] || {}).name || '?',
                    parent_relation: rel,
                });
            }
            if (rel === 'sibling' && (pid === memberId || sid === memberId)) {
                const otherId = pid === memberId ? sid : pid;
                family.siblings.push({
                    relation_id: r.id,
                    id:          otherId,
                    name:        (allMem[otherId] || {}).name || '?',
                });
            }
        });

        return family;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    function _clearAddForm() {
        const relSelect    = document.getElementById('mFieldRelationship');
        const memberSelect = document.getElementById('mFieldRelatedMember');
        const addBtn       = document.getElementById('mBtnAddRelation');
        if (relSelect)    relSelect.value = '';
        if (memberSelect) {
            memberSelect.innerHTML = '<option value="">– erst Beziehung wählen –</option>';
            memberSelect.disabled  = true;
        }
        if (addBtn) addBtn.disabled = true;
        _hideAddError();
    }

    function _showAddError(msg) {
        const el = document.getElementById('mFamilyAddError');
        if (!el) return;
        el.textContent = msg;
        el.classList.remove('is-hidden');
    }

    function _hideAddError() {
        const el = document.getElementById('mFamilyAddError');
        if (el) el.classList.add('is-hidden');
    }

    function _esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

}());
