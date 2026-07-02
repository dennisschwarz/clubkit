/**
 * ClubKit YouthClubMode – Family-Tab Logic
 *
 * Extends the Member modal with the Family tab.
 * Communicates with members-modal.js via the ckOn/ckEmit system.
 *
 * Dependencies:
 *   - window.CK_Members       (data bridge: members with family{} object per entry)
 *   - window.CK_YouthClubMode (data bridge: csrf, routes, allMembers, relations)
 *   - window.CK_Lang          (localised notification strings from layout.blade.php)
 *   - ckOn(), ckTabEnable(), ckConfirm(), ckNotify()  from resources/js/app.js
 *
 * Rule: classList operations only – no el.style.*
 *
 * Load-order note:
 *   app.js loads as type="module" (deferred).
 *   This script runs synchronously at the body end → ckOn is not yet defined.
 *   Therefore: wrap ckOn registrations inside DOMContentLoaded.
 */
(function () {
    'use strict';

    // ── Module state ──────────────────────────────────────────────────────────
    let currentMemberId = null;

    // Shorthand: read from the localised notification bridge.
    function _lang(key, fallback) {
        return (((window.CK_Lang || {}).notifications || {})[key]) || fallback;
    }

    // ── DOMContentLoaded: register event listeners ────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {

        // Listen for modal-open events
        ckOn('member.modal.open', function (detail) {
            currentMemberId = detail.memberId || null;

            if (detail.mode === 'create') {
                ckTabEnable('memberFamilyTabBtn', 'memberFamilyCreateHint', false);
                _clearAddForm();
                return;
            }

            // Edit mode: activate tab + render list
            ckTabEnable('memberFamilyTabBtn', 'memberFamilyCreateHint', true);
            _clearAddForm();
            _renderFamilyList();
        });

        // Relationship dropdown: filter the member dropdown
        const relSelect = document.getElementById('mFieldRelationship');
        if (relSelect) {
            relSelect.addEventListener('change', _onRelationshipChange);
        }

        // Member dropdown: enable the add button
        const memberSelect = document.getElementById('mFieldRelatedMember');
        if (memberSelect) {
            memberSelect.addEventListener('change', function () {
                const addBtn = document.getElementById('mBtnAddRelation');
                if (addBtn) addBtn.disabled = !memberSelect.value;
            });
        }

        // Add button
        const addBtn = document.getElementById('mBtnAddRelation');
        if (addBtn) {
            addBtn.addEventListener('click', _onAddRelation);
        }

    }); // end DOMContentLoaded

    // ── Dropdown 1 changed: populate dropdown 2 ───────────────────────────────

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
        if (addBtn) addBtn.disabled = true; // enable only after member selection
    }

    // ── Filter logic ──────────────────────────────────────────────────────────

    /**
     * Filters allMembers by the rules of the selected relationship type.
     * @param  {string} relationshipType  'father'|'mother'|'father_of'|'mother_of'|'sibling'
     * @return {Array}  Filtered members [{id, name, ...}]
     */
    function _getFilteredMembers(relationshipType) {
        const ycm       = window.CK_YouthClubMode || {};
        const allMem    = ycm.allMembers  || {};
        const relations = ycm.relations   || [];
        const all       = Object.values(allMem);

        // Always: exclude self
        let result = all.filter(function (m) { return m.id !== currentMemberId; });

        switch (relationshipType) {

            case 'father':
                // Adult (or no date of birth) + male/diverse/unspecified
                // + not yet the current member's father
                result = result.filter(function (m) {
                    return _isAdultOrNoDob(m)
                        && _isGender(m, ['male', 'diverse', null])
                        && !_relationExists(m.id, currentMemberId, 'father', relations);
                });
                break;

            case 'mother':
                // Adult + female/diverse/unspecified
                // + not yet the current member's mother
                result = result.filter(function (m) {
                    return _isAdultOrNoDob(m)
                        && _isGender(m, ['female', 'diverse', null])
                        && !_relationExists(m.id, currentMemberId, 'mother', relations);
                });
                break;

            case 'father_of':
                // Members that don't yet have a father
                result = result.filter(function (m) {
                    return !_hasParent(m.id, 'father', relations);
                });
                break;

            case 'mother_of':
                // Members that don't yet have a mother
                result = result.filter(function (m) {
                    return !_hasParent(m.id, 'mother', relations);
                });
                break;

            case 'sibling':
                // All members that are not yet siblings of the current member
                result = result.filter(function (m) {
                    return !_areSiblings(currentMemberId, m.id, relations);
                });
                break;
        }

        return result.sort(function (a, b) {
            return a.name.localeCompare(b.name, 'de');
        });
    }

    // Is m.age >= 18 (or no date of birth)?
    function _isAdultOrNoDob(m) {
        if (!m.date_of_birth) return true; // no date of birth → still show
        const dob   = new Date(m.date_of_birth);
        const ageMs = Date.now() - dob.getTime();
        return ageMs / (1000 * 60 * 60 * 24 * 365.25) >= 18;
    }

    // Does m.gender have one of the allowed values?
    function _isGender(m, allowed) {
        return allowed.indexOf(m.gender) !== -1;
    }

    // Does the relation already exist: primary=parentId, secondary=childId, relationship=rel?
    function _relationExists(parentId, childId, rel, relations) {
        return relations.some(function (r) {
            return r.primary_member_id   === parentId
                && r.secondary_member_id === childId
                && r.relationship        === rel;
        });
    }

    // Does memberId already have a parent of type rel?
    function _hasParent(memberId, rel, relations) {
        return relations.some(function (r) {
            return r.secondary_member_id === memberId && r.relationship === rel;
        });
    }

    // Are id1 and id2 already siblings?
    function _areSiblings(id1, id2, relations) {
        return relations.some(function (r) {
            return r.relationship === 'sibling'
                && ((r.primary_member_id === id1 && r.secondary_member_id === id2)
                 || (r.primary_member_id === id2 && r.secondary_member_id === id1));
        });
    }

    // ── Add relation (AJAX) ───────────────────────────────────────────────────

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
                // Inline error: shown within the modal (sub-action feedback, not a global notification).
                _showAddError(data.message || _lang('relation_add_error', 'Fehler beim Speichern.'));
                if (addBtn) addBtn.disabled = false;
                return;
            }

            // Add new relation to local state
            (window.CK_YouthClubMode.relations || []).push(data.relation);

            // Reset form + re-render list
            _clearAddForm();
            _renderFamilyList();

            // Brief success toast – the modal stays open (sub-action, not a full save).
            ckNotify('success', _lang('relation_added', 'Verbindung gespeichert.'));
        })
        .catch(function () {
            if (addBtn) {
                addBtn.classList.remove('ck-btn--loading');
                addBtn.disabled = false;
            }
            _showAddError(_lang('network_error', 'Netzwerkfehler. Bitte versuche es erneut.'));
        });
    }

    // ── Remove relation (AJAX) ────────────────────────────────────────────────

    /**
     * Opens the global confirm modal before sending the DELETE request.
     * Uses window.ckConfirm() from app.js instead of the blocking browser
     * confirm() dialog – consistent with all other delete confirmations.
     *
     * Called from onclick in the dynamically rendered family list HTML.
     *
     * @param {number} relationId
     */
    window.ckYcmDeleteRelation = function (relationId) {
        window.ckConfirm('Verbindung wirklich entfernen?', function () {
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
                    ckNotify('error', data.message || _lang('relation_delete_error', 'Fehler beim Löschen.'));
                    return;
                }

                // Remove relation from local state and re-render the list
                window.CK_YouthClubMode.relations = (ycm.relations || []).filter(function (r) {
                    return r.id !== data.relation_id;
                });

                _renderFamilyList();
                ckNotify('success', _lang('relation_removed', 'Verbindung entfernt.'));
            })
            .catch(function () {
                ckNotify('error', _lang('network_error', 'Netzwerkfehler. Bitte versuche es erneut.'));
            });
        });
    };

    // ── Render family list ────────────────────────────────────────────────────

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
     * Computes the family data for a member from the relations array.
     * Mirrors the logic in FamilyService::buildFamilyData().
     */
    function _buildFamily(memberId, relations, allMem) {
        const family = { father: null, mother: null, children: [], siblings: [] };

        relations.forEach(function (r) {
            const pid = r.primary_member_id;
            const sid = r.secondary_member_id;
            const rel = r.relationship;

            const otherIdAsPrimary   = (pid === memberId) ? sid : null;
            const otherIdAsSecondary = (sid === memberId) ? pid : null;

            if (rel === 'father' || rel === 'mother') {
                if (pid === memberId) {
                    // memberId is the parent
                    const child = allMem[sid];
                    if (child) {
                        family.children.push({
                            name:           child.name,
                            relation_id:    r.id,
                            parent_relation: rel,
                        });
                    }
                } else if (sid === memberId) {
                    // memberId is the child
                    const parent = allMem[pid];
                    if (parent) {
                        if (rel === 'father') family.father = { name: parent.name, relation_id: r.id };
                        else                  family.mother = { name: parent.name, relation_id: r.id };
                    }
                }
            } else if (rel === 'sibling') {
                const otherId = otherIdAsPrimary !== null ? otherIdAsPrimary : otherIdAsSecondary;
                if (otherId !== null) {
                    const sib = allMem[otherId];
                    if (sib) family.siblings.push({ name: sib.name, relation_id: r.id });
                }
            }
        });

        return family;
    }

    // ── Error helpers ─────────────────────────────────────────────────────────

    function _showAddError(msg) {
        const el = document.getElementById('mAddRelationError');
        if (!el) return;
        el.textContent = msg;
        el.classList.remove('is-hidden');
    }

    function _hideAddError() {
        const el = document.getElementById('mAddRelationError');
        if (el) el.classList.add('is-hidden');
    }

    function _clearAddForm() {
        const relSelect    = document.getElementById('mFieldRelationship');
        const memberSelect = document.getElementById('mFieldRelatedMember');
        const addBtn       = document.getElementById('mBtnAddRelation');

        if (relSelect) relSelect.value = '';
        if (memberSelect) {
            memberSelect.innerHTML = '<option value="">– erst Beziehung wählen –</option>';
            memberSelect.disabled  = true;
        }
        if (addBtn) addBtn.disabled = true;
        _hideAddError();
    }

    // ── Escape helper ──────────────────────────────────────────────────────────

    function _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

}());
