document.addEventListener('DOMContentLoaded', function() {
    console.log('Custom Advance Repeater: DOM loaded, starting initialization');
    
    // --- Logic for Field Group Editing Page (If present) ---
    if (typeof car_field_group_config !== 'undefined') {
        (function($) {
            let fieldIndex = car_field_group_config.fields_count;
            
            // Display logic toggle
            const displayLogicRadios = document.querySelectorAll('input[name="display_logic"]');
            const displayOptions = document.querySelectorAll('.display-options');
            const pagesSection = document.querySelector('.pages-section');
            
            const allPostTypesCheckbox = document.querySelector('.all-post-types');
            const postTypesContainer = document.querySelector('.post-types-container');
            
            function toggleDisplayOptions() {
                const selectedValue = document.querySelector('input[name="display_logic"]:checked').value;
                if (selectedValue === 'post_types') {
                    displayOptions.forEach(opt => opt.style.display = 'table-row');
                    
                    const allSelected = car_field_group_config.is_all_types;
                    if (allSelected && allPostTypesCheckbox) {
                        allPostTypesCheckbox.checked = true;
                        postTypesContainer.style.display = 'none';
                        pagesSection.style.display = 'none';
                    } else {
                        checkPagesPostType();
                    }
                } else {
                    displayOptions.forEach(opt => opt.style.display = 'none');
                }
            }
            
            if (displayLogicRadios.length > 0) {
                displayLogicRadios.forEach(radio => {
                    radio.addEventListener('change', toggleDisplayOptions);
                });
                toggleDisplayOptions();
            }
            
            if (allPostTypesCheckbox) {
                allPostTypesCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        postTypesContainer.style.display = 'none';
                        document.querySelectorAll('.post-type-checkbox').forEach(cb => {
                            cb.checked = false;
                        });
                        pagesSection.style.display = 'none';
                    } else {
                        postTypesContainer.style.display = 'block';
                        checkPagesPostType();
                    }
                });
                
                if (car_field_group_config.is_all_types) {
                    allPostTypesCheckbox.checked = true;
                    postTypesContainer.style.display = "none";
                    pagesSection.style.display = "none";
                }
            }
            
            function checkPagesPostType() {
                const pageCheckbox = document.querySelector('.post-type-checkbox[data-post-type="page"]');
                
                if (pageCheckbox && pageCheckbox.checked) {
                    pagesSection.style.display = 'table-row';
                } else {
                    pagesSection.style.display = 'none';
                    if (!pageCheckbox || !pageCheckbox.checked) {
                        const container = document.querySelector('#selected-pages-container');
                        if(container) container.innerHTML = '';
                    }
                }
            }
            
            document.querySelectorAll('.post-type-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    checkPagesPostType();
                    
                    if (allPostTypesCheckbox && allPostTypesCheckbox.checked) {
                        allPostTypesCheckbox.checked = false;
                        postTypesContainer.style.display = 'block';
                    }
                });
            });
            
            // Page selection modal logic
            const selectPagesBtn = document.getElementById('select-pages-btn');
            const clearPagesBtn = document.getElementById('clear-pages-btn');
            const pagesModal = document.getElementById('pages-modal');
            const closeModalBtn = document.getElementById('close-modal');
            const pageSearch = document.getElementById('page-search');
            const pagesList = document.getElementById('pages-list');
            const addSelectedPagesBtn = document.getElementById('add-selected-pages');
            const selectedPagesContainer = document.getElementById('selected-pages-container');
            
            function loadPages(search = '') {
                pagesList.innerHTML = '<p>' + car_field_group_config.i18n.loading + '</p>';
                
                const data = new FormData();
                data.append('action', 'car_get_pages');
                data.append('search', search);
                data.append('nonce', car_admin_vars.ajax_nonce);
                
                fetch(car_admin_vars.ajax_url, {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        pagesList.innerHTML = '';
                        if (data.data.length > 0) {
                            data.data.forEach(page => {
                                const pageDiv = document.createElement('div');
                                pageDiv.className = 'page-item';
                                pageDiv.style.cssText = 'padding: 8px; border-bottom: 1px solid #eee;';
                                
                                const label = document.createElement('label');
                                label.style.cssText = 'display: flex; align-items: center; cursor: pointer;';
                                
                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.value = page.ID;
                                checkbox.className = 'page-checkbox';
                                
                                const existingPages = selectedPagesContainer.querySelectorAll('input[name="pages[]"]');
                                Array.from(existingPages).forEach(input => {
                                    if (input.value == page.ID) {
                                        checkbox.checked = true;
                                    }
                                });
                                
                                const titleSpan = document.createElement('span');
                                titleSpan.textContent = page.post_title + ' (ID: ' + page.ID + ')';
                                titleSpan.style.marginLeft = '8px';
                                
                                label.appendChild(checkbox);
                                label.appendChild(titleSpan);
                                pageDiv.appendChild(label);
                                pagesList.appendChild(pageDiv);
                            });
                        } else {
                            pagesList.innerHTML = '<p>' + car_field_group_config.i18n.no_pages + '</p>';
                        }
                    } else {
                        pagesList.innerHTML = '<p>' + car_field_group_config.i18n.error + '</p>';
                    }
                })
                .catch(error => {
                    pagesList.innerHTML = '<p>' + car_field_group_config.i18n.error + '</p>';
                });
            }
            
            if (selectPagesBtn) {
                selectPagesBtn.addEventListener('click', function() {
                    pagesModal.style.display = 'block';
                    loadPages();
                });
            }
            
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function() {
                    pagesModal.style.display = 'none';
                });
            }
            
            if (pagesModal) {
                window.addEventListener('click', function(event) {
                    if (event.target === pagesModal) {
                        pagesModal.style.display = 'none';
                    }
                });
            }
            
            if (pageSearch) {
                pageSearch.addEventListener('input', function() {
                    loadPages(this.value);
                });
            }
            
            if (addSelectedPagesBtn) {
                addSelectedPagesBtn.addEventListener('click', function() {
                    const selectedCheckboxes = pagesList.querySelectorAll('.page-checkbox:checked');
                    selectedCheckboxes.forEach(checkbox => {
                        const pageId = checkbox.value;
                        
                        if (!selectedPagesContainer.querySelector(`[data-page-id="${pageId}"]`)) {
                            const pageTitle = checkbox.parentElement.querySelector('span').textContent.split(' (ID:')[0];
                            
                            const pageDiv = document.createElement('div');
                            pageDiv.className = 'selected-page';
                            pageDiv.dataset.pageId = pageId;
                            pageDiv.style.cssText = 'margin-bottom: 5px; padding: 5px; background: #fff; border: 1px solid #ddd;';
                            
                            pageDiv.innerHTML = `
                                <span>${pageTitle}</span>
                                <input type="hidden" name="pages[]" value="${pageId}">
                                <a href="#" class="remove-page" style="color: #dc3232; text-decoration: none; margin-left: 10px;">×</a>
                            `;
                            
                            selectedPagesContainer.appendChild(pageDiv);
                        }
                    });
                    
                    pagesList.querySelectorAll('.page-checkbox').forEach(cb => {
                        cb.checked = false;
                    });
                    
                    pagesModal.style.display = 'none';
                });
            }
            
            if (clearPagesBtn) {
                clearPagesBtn.addEventListener('click', function() {
                    if (confirm(car_field_group_config.i18n.confirm_clear)) {
                        selectedPagesContainer.innerHTML = '';
                    }
                });
            }
            
            if (selectedPagesContainer) {
                selectedPagesContainer.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-page')) {
                        e.preventDefault();
                        e.target.closest('.selected-page').remove();
                    }
                });
            }

            // --- Dynamic Field Creation Logic ---
            
            // Add field
            const addFieldBtn = document.getElementById('car-add-field');
            if (addFieldBtn) {
                addFieldBtn.addEventListener('click', function() {
                    const container = document.getElementById('car-fields-container');
                    const newFieldRow = createFieldRow(fieldIndex);
                    container.appendChild(newFieldRow);
                    fieldIndex++;
                    
                    updateFieldIndices();
                });
            }
            
            // Remove field
            document.addEventListener('click', function(e) {
                if (e.target.closest('.car-remove-field')) {
                    e.preventDefault();
                    if (confirm(car_field_group_config.i18n.confirm_remove_field)) {
                        const row = e.target.closest('.car-field-row');
                        row.remove();
                        updateFieldIndices();
                    }
                }
            });
            
            // Remove subfield
            document.addEventListener('click', function(e) {
                if (e.target.closest('.car-remove-subfield')) {
                    e.preventDefault();
                    if (confirm(car_field_group_config.i18n.confirm_remove_subfield)) {
                        const row = e.target.closest('.car-subfield-row');
                        row.remove();
                    }
                }
            });
            
            // Remove nested subfield
            document.addEventListener('click', function(e) {
                if (e.target.closest('.car-remove-nested-subfield')) {
                    e.preventDefault();
                    if (confirm(car_field_group_config.i18n.confirm_remove_nested)) {
                        const row = e.target.closest('.car-nested-subfield-row');
                        row.remove();
                    }
                }
            });

            // Handle Label to Name Conversion
            function handleLabelToNameConversion(target) {
                let isLabelField = false;
                let fieldLevel = null;
                
                if (target.classList.contains('car-field-label')) {
                    isLabelField = true;
                    fieldLevel = 0;
                } 
                else if (target.name && target.name.includes('[label]')) {
                    isLabelField = true;
                    const nameStr = target.name;
                    const subfieldsCount = (nameStr.match(/\[subfields\]/g) || []).length;
                    
                    if (subfieldsCount === 1) {
                        fieldLevel = 1;
                    } else if (subfieldsCount >= 2) {
                        fieldLevel = 2;
                    } else {
                        fieldLevel = 0;
                    }
                }
                
                if (!isLabelField) {
                    return;
                }
                
                const label = target.value;
                let name = '';
                
                if (label && label.trim() !== '') {
                    name = label.toLowerCase();
                    name = name.replace(/[^a-z0-9\s]/g, ' ');
                    name = name.replace(/\s+/g, ' ');
                    name = name.trim();
                    name = name.replace(/\s/g, '_');
                    name = name.replace(/_+/g, '_');
                    name = name.replace(/^_+|_+$/g, '');
                    
                    if (name === '') {
                        name = 'field_' + Math.floor(Math.random() * 1000);
                    }
                } else {
                    return;
                }
                
                let nameField = null;
                
                switch (fieldLevel) {
                    case 0:
                        const row = target.closest('.car-field-row');
                        nameField = row?.querySelector('.car-field-name');
                        break;
                    case 1:
                        const subRow = target.closest('.car-subfield-row');
                        nameField = subRow?.querySelector('input[name*="[name]"]');
                        break;
                    case 2:
                        const nestedRow = target.closest('.car-nested-subfield-row');
                        nameField = nestedRow?.querySelector('input[name*="[name]"]');
                        break;
                }
                
                if (nameField) {
                    nameField.value = name;
                    const event = new Event('change', { bubbles: true });
                    nameField.dispatchEvent(event);
                }
            }

            // Event listeners for all field levels
            document.addEventListener('input', function(e) {
                clearTimeout(window.carDebounceTimer);
                window.carDebounceTimer = setTimeout(() => {
                    handleLabelToNameConversion(e.target);
                }, 100);
            });
            
            document.addEventListener('keyup', function(e) {
                if (e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete' || e.key === ' ') {
                    clearTimeout(window.carDebounceTimer);
                    window.carDebounceTimer = setTimeout(() => {
                        handleLabelToNameConversion(e.target);
                    }, 50);
                }
            });
            
            document.addEventListener('paste', function(e) {
                setTimeout(() => {
                    handleLabelToNameConversion(e.target);
                }, 10);
            });

            // Initialize existing fields on page load
            function initializeExistingFields() {
                // Process existing main fields
                document.querySelectorAll('.car-field-label').forEach(function(input) {
                    setTimeout(() => {
                        handleLabelToNameConversion(input);
                    }, 50);
                });
                
                // Process existing subfields
                document.querySelectorAll('.car-subfield-row input[name*="[label]"]').forEach(function(input) {
                    setTimeout(() => {
                        handleLabelToNameConversion(input);
                    }, 50);
                });
                
                // Process existing nested2 subfields
                document.querySelectorAll('.car-nested-subfield-row input[name*="[label]"]').forEach(function(input) {
                    setTimeout(() => {
                        handleLabelToNameConversion(input);
                    }, 50);
                });
            }
            
            setTimeout(initializeExistingFields, 500);

            // Show/hide options based on field type
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('car-field-type')) {
                    const row = e.target.closest('.car-field-row');
                    const type = e.target.value;
                    const optionsDiv = row.querySelector('.car-field-options');
                    
                    if (['select', 'checkbox', 'radio'].includes(type)) {
                        optionsDiv.style.display = 'block';
                        const currentIndex = Array.from(document.querySelectorAll('.car-field-row')).indexOf(row);
                        optionsDiv.innerHTML = `
                            <label>${car_field_group_config.i18n.options_label}</label>
                            <textarea name="fields[${currentIndex}][options]" class="widefat" rows="3" placeholder="My Option 1"></textarea>
                            <p class="description">${car_field_group_config.i18n.options_desc}</p>
                        `;
                    } else if (type === 'repeater') {
                        optionsDiv.style.display = 'block';
                        const currentIndex = Array.from(document.querySelectorAll('.car-field-row')).indexOf(row);
                        optionsDiv.innerHTML = `
                            <label style=" font-size: 16px;font-weight: 600;">${car_field_group_config.i18n.sub_fields_label}</label>
                            <div class="car-subfields-container" style="margin-top: 6px" data-parent-index="${currentIndex}">
                                </div>
                            <button type="button" class="button button-small car-add-subfield add_field_btn" data-parent-index="${currentIndex}">
                                <span class="dashicons dashicons-plus"></span> ${car_field_group_config.i18n.add_sub_field}
                            </button>
                            <p class="description">${car_field_group_config.i18n.add_fields_desc}</p>
                        `;
                    } else {
                        optionsDiv.style.display = 'none';
                    }
                }
            });

            // Handle subfield type change
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('car-subfield-type')) {
                    const row = e.target.closest('.car-subfield-row');
                    const type = e.target.value;
                    const optionsDiv = row.querySelector('.car-subfield-options');
                    
                    if (['select', 'checkbox', 'radio', 'repeater'].includes(type)) {
                        optionsDiv.style.display = 'block';
                        
                        if (type === 'repeater') {
                            const fieldRow = row.closest('.car-field-row');
                            const parentIndex = Array.from(document.querySelectorAll('.car-field-row')).indexOf(fieldRow);
                            const subIndex = Array.from(fieldRow.querySelectorAll('.car-subfield-row')).indexOf(row);
                            
                            optionsDiv.innerHTML = `
                                <label style=" font-size: 16px;font-weight: 600;">${car_field_group_config.i18n.sub_fields_label}</label>
                                <div class="car-subfields-container" data-parent-index="${parentIndex}" data-sub-index="${subIndex}">
                                    </div>
                                <button type="button" class="button button-small car-add-nested-subfield add_field_btn" data-parent-index="${parentIndex}" data-sub-index="${subIndex}">
                                    <span class="dashicons dashicons-plus"></span> ${car_field_group_config.i18n.add_sub_field}
                                </button>
                                <p class="description">${car_field_group_config.i18n.add_fields_desc}</p>
                            `;
                        }
                    } else {
                        optionsDiv.style.display = 'none';
                    }
                }
            });

            // Add subfield
            document.addEventListener('click', function(e) {
                if (e.target.closest('.car-add-subfield')) {
                    e.preventDefault();
                    const button = e.target.closest('.car-add-subfield');
                    const parentIndex = button.dataset.parentIndex;
                    const container = button.parentElement.querySelector('.car-subfields-container');
                    
                    const subfieldsCount = container.querySelectorAll('.car-subfield-row').length;
                    
                    const newSubfield = createSubfieldRow(parentIndex, subfieldsCount);
                    container.appendChild(newSubfield);
                    
                    setTimeout(() => {
                        const labelInput = newSubfield.querySelector('input[name*="[label]"]');
                        if (labelInput) {
                            labelInput.addEventListener('input', function() {
                                handleLabelToNameConversion(this);
                            });
                        }
                    }, 100);
                }
            });

            // Add nested subfield
            document.addEventListener('click', function(e) {
                if (e.target.closest('.car-add-nested-subfield')) {
                    e.preventDefault();
                    const button = e.target.closest('.car-add-nested-subfield');
                    const parentIndex = button.dataset.parentIndex;
                    const subIndex = button.dataset.subIndex;
                    const container = button.parentElement.querySelector('.car-subfields-container');
                    
                    const nestedSubfieldsCount = container.querySelectorAll('.car-nested-subfield-row').length;
                    const newNestedSubfield = createNestedSubfieldRow(parentIndex, subIndex, nestedSubfieldsCount);
                    container.appendChild(newNestedSubfield);
                    
                    setTimeout(() => {
                        const labelInput = newNestedSubfield.querySelector('input[name*="[label]"]');
                        if (labelInput) {
                            labelInput.addEventListener('input', function() {
                                handleLabelToNameConversion(this);
                            });
                        }
                    }, 100);
                }
            });

            // Initialize field type changes
            document.querySelectorAll('.car-field-type').forEach(function(select) {
                select.dispatchEvent(new Event('change'));
            });
            
            // Initialize subfield type changes
            document.querySelectorAll('.car-subfield-type').forEach(function(select) {
                select.dispatchEvent(new Event('change'));
            });

            // --- HTML Generators ---
            function createFieldRow(index) {
                const div = document.createElement('div');
                div.className = 'car-field-row';
                div.innerHTML = `
                    <div class="panel_row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h3 class="top_left_panel" style="margin: 0;">${car_field_group_config.i18n.field_title} #<span class="field-index">${index + 1}</span></h3>
                        <div class="top_right_panel">
                            <div class="required_checkbox">
                                <label>
                                    <input type="checkbox" name="fields[${index}][required]" value="1">
                                    ${car_field_group_config.i18n.required_field}
                                </label>
                            </div>
                                <a href="#" class="car-remove-field" style="color: #dc3232; text-decoration: none;">
                                <span class="dashicons dashicons-trash"></span> ${car_field_group_config.i18n.remove}
                            </a>
                        </div>
                       
                    </div>
                    
                    <div class="inner_row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_label} *</label>
                            <select name="fields[${index}][type]" class="car-field-type widefat" required>
                                <option value="text">${car_field_group_config.i18n.text}</option>
                                <option value="textarea">${car_field_group_config.i18n.textarea}</option>
                                <option value="image">${car_field_group_config.i18n.image}</option>
                                <option value="select">${car_field_group_config.i18n.select}</option>
                                <option value="checkbox">${car_field_group_config.i18n.checkbox}</option>
                                <option value="radio">${car_field_group_config.i18n.radio}</option>
                                <option value="color">${car_field_group_config.i18n.color}</option>
                                <option value="date">${car_field_group_config.i18n.date}</option>
                                <option value="repeater">${car_field_group_config.i18n.repeater}</option>
                            </select>
                        </div>
                        
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_label} *</label>
                            <input type="text" name="fields[${index}][label]" class="car-field-label widefat" required placeholder="${car_field_group_config.i18n.field_label_placeholder}">
                        </div>
                        
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_name} *</label>
                            <input type="text" name="fields[${index}][name]" class="car-field-name widefat" required placeholder="${car_field_group_config.i18n.field_name_placeholder}">
                            <p class="description">${car_field_group_config.i18n.field_name_desc}</p>
                        </div>
                    </div>
                    
                    <div class="car-field-options" style="display: none; margin-bottom: 15px;">
                        </div>
                `;
                
                const labelInput = div.querySelector('.car-field-label');
                const nameInput = div.querySelector('.car-field-name');
                
                if (labelInput && nameInput) {
                    labelInput.addEventListener('input', function() {
                        handleLabelToNameConversion(this);
                    });
                }
                
                return div;
            }

            function createSubfieldRow(parentIndex, subIndex) {
                const div = document.createElement('div');
                div.className = 'car-subfield-row';
                div.style.cssText = 'border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;';
                div.innerHTML = `
                    <div class="panel_row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <strong class="top_left_panel">${car_field_group_config.i18n.sub_field_title}</strong>
                        <div class="top_right_panel">
                             <div class="required_checkbox">
                                <label>
                                    <input type="checkbox" name="fields[${parentIndex}][subfields][${subIndex}][required]" value="1">
                                    ${car_field_group_config.i18n.required_field}
                                </label>
                            </div>
                            <a href="#" class="car-remove-subfield" style="color: #dc3232; text-decoration: none;">
                                <span class="dashicons dashicons-trash"></span> ${car_field_group_config.i18n.remove}
                            </a>
                        </div>
                        
                    </div>
                    
                    <div class="inner_row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_label} *</label>
                            <select name="fields[${parentIndex}][subfields][${subIndex}][type]" class="widefat car-subfield-type" required>
                                <option value="text">${car_field_group_config.i18n.text}</option>
                                <option value="textarea">${car_field_group_config.i18n.textarea}</option>
                                <option value="image">${car_field_group_config.i18n.image}</option>
                                <option value="select">${car_field_group_config.i18n.select}</option>
                                <option value="checkbox">${car_field_group_config.i18n.checkbox}</option>
                                <option value="radio">${car_field_group_config.i18n.radio}</option>
                                <option value="repeater">${car_field_group_config.i18n.repeater}</option>
                            </select>
                        </div>
                        
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_label} *</label>
                            <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][label]" class="widefat" required placeholder="${car_field_group_config.i18n.sub_label_placeholder}">
                        </div>
                        
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_name} *</label>
                            <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][name]" class="widefat" required placeholder="${car_field_group_config.i18n.sub_name_placeholder}">
                            <p class="description">${car_field_group_config.i18n.field_name_desc}</p>
                        </div>
                    </div>
                    
                    <div class="car-subfield-options" style="display: none; margin-bottom: 10px;">
                    </div>
                    
                   
                `;
                
                const labelInput = div.querySelector('input[name*="[label]"]');
                const nameInput = div.querySelector('input[name*="[name]"]');
                
                if (labelInput && nameInput) {
                    labelInput.addEventListener('input', function() {
                        handleLabelToNameConversion(this);
                    });
                }
                
                return div;
            }

            function createNestedSubfieldRow(parentIndex, subIndex, nestedIndex) {
                const div = document.createElement('div');
                div.className = 'car-nested-subfield-row';
                div.style.cssText = 'border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; margin-top: 6px; background: #f0f0f0;';
                div.innerHTML = `
                    <div class="panel_row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <strong  class="top_left_panel">${car_field_group_config.i18n.nested_sub_field_title}</strong>
                        <div class="top_right_panel">
                            <div class="required_checkbox">
                                <label>
                                    <input type="checkbox" name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][required]" value="1">
                                    ${car_field_group_config.i18n.required_field}
                                </label>
                            </div>
                            <a href="#" class="car-remove-nested-subfield" style="color: #dc3232; text-decoration: none;">
                                <span class="dashicons dashicons-trash"></span> ${car_field_group_config.i18n.remove}
                            </a>
                        </div>
                    </div>
                    
                    <div class="inner_row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_label} *</label>
                            <select name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][type]" class="widefat" required>
                                <option value="text">${car_field_group_config.i18n.text}</option>
                                <option value="textarea">${car_field_group_config.i18n.textarea}</option>
                                <option value="image">${car_field_group_config.i18n.image}</option>
                                <option value="select">${car_field_group_config.i18n.select}</option>
                                <option value="checkbox">${car_field_group_config.i18n.checkbox}</option>
                                <option value="radio">${car_field_group_config.i18n.radio}</option>
                            </select>
                        </div>
                        
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_label} *</label>
                            <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][label]" class="widefat" required placeholder="${car_field_group_config.i18n.nested_label_placeholder}">
                        </div>
                        
                        <div class="inner_colm">
                            <label>${car_field_group_config.i18n.field_name} *</label>
                            <input type="text" name="fields[${parentIndex}][subfields][${subIndex}][subfields][${nestedIndex}][name]" class="widefat" required placeholder="${car_field_group_config.i18n.nested_name_placeholder}">
                            <p class="description">${car_field_group_config.i18n.field_name_desc}</p>
                        </div>
                    </div>
                    
                    
                `;
                
                const labelInput = div.querySelector('input[name*="[label]"]');
                const nameInput = div.querySelector('input[name*="[name]"]');
                
                if (labelInput && nameInput) {
                    labelInput.addEventListener('input', function() {
                        handleLabelToNameConversion(this);
                    });
                }
                
                return div;
            }

            function updateFieldIndices() {
                const rows = document.querySelectorAll('.car-field-row');
                rows.forEach(function(row, index) {
                    row.querySelector('.field-index').textContent = index + 1;
                    
                    const inputs = row.querySelectorAll('[name]');
                    inputs.forEach(function(input) {
                        const oldName = input.name;
                        const match = oldName.match(/fields\[(\d+)\]/);
                        if (match) {
                            const newName = oldName.replace(/fields\[\d+\]/g, `fields[${index}]`);
                            input.name = newName;
                        }
                    });
                    
                    const subfieldsContainer = row.querySelector('.car-subfields-container');
                    if (subfieldsContainer) {
                        subfieldsContainer.dataset.parentIndex = index;
                    }
                    
                    const addSubfieldBtn = row.querySelector('.car-add-subfield');
                    if (addSubfieldBtn) {
                        addSubfieldBtn.dataset.parentIndex = index;
                    }
                });
                fieldIndex = rows.length;
            }
        })(jQuery);
    } // End Field Group Editing logic

    
    // --- Logic for Repeater Initialization on Posts/Pages ---
    (function($) {
        'use strict';
        
        function carLog(msg) {
            console.log('Custom Advance Repeater: ' + msg);
        }
        
        function isJQueryUILoaded() {
            return typeof $.ui !== 'undefined' && 
                   typeof $.ui.sortable !== 'undefined' && 
                   typeof $.ui.datepicker !== 'undefined';
        }
        
        function waitForJQueryUI(callback) {
            var attempts = 0;
            var maxAttempts = 10;
            
            function check() {
                attempts++;
                if (isJQueryUILoaded()) {
                    carLog('jQuery UI loaded successfully');
                    callback(true);
                } else if (attempts < maxAttempts) {
                    setTimeout(check, 500);
                } else {
                    carLog('ERROR: jQuery UI not loaded after ' + maxAttempts + ' attempts');
                    callback(false);
                }
            }
            
            check();
        }
        
        function initDatepicker() {
            if (typeof $.fn.datepicker === 'function') {
                // Initialize all datepickers with proper calendar settings
                $('.car-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    yearRange: '-100:+10',
                    showButtonPanel: true,
                    showOtherMonths: true,
                    selectOtherMonths: true,
                    // Fix calendar positioning and styling
                    beforeShow: function(input, inst) {
                        setTimeout(function() {
                            if (inst.dpDiv) {
                                inst.dpDiv.css({
                                    'z-index': '100001',
                                    'font-size': '13px',
                                    'line-height': '1.4',
                                    'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
                                });
                                
                                // Fix header styles
                                inst.dpDiv.find('.ui-datepicker-header').css({
                                    'background': '#f8f9fa',
                                    'border': 'none',
                                    'border-radius': '3px',
                                    'padding': '8px 0',
                                    'margin-bottom': '8px'
                                });
                                
                                // Fix calendar table styles
                                inst.dpDiv.find('.ui-datepicker-calendar').css({
                                    'width': '100%',
                                    'border-collapse': 'collapse',
                                    'margin': '0'
                                });
                                
                                // Fix navigation buttons
                                inst.dpDiv.find('.ui-datepicker-prev, .ui-datepicker-next').css({
                                    'cursor': 'pointer',
                                    'top': '8px',
                                    'width': '30px',
                                    'height': '30px',
                                    'border': '1px solid #ddd',
                                    'border-radius': '3px',
                                    'background': 'white'
                                });
                            }
                        }, 0);
                        
                        return {};
                    },
                    // Fix for showing calendar properly
                    onClose: function(dateText, inst) {
                        // Clean up
                    }
                });
                
                carLog('Datepickers initialized with calendar fix');
            } else {
                carLog('ERROR: Datepicker function not available');
            }
        }
        
        function initColorpicker() {
            if (typeof $.fn.wpColorPicker === 'function') {
                $('.car-colorpicker').each(function() {
                    if (!$(this).hasClass('wp-color-picker')) {
                        $(this).wpColorPicker();
                    }
                });
                carLog('Colorpickers initialized');
            }
        }
        
        function initImageUpload() {
            // Use event delegation for dynamically created upload buttons
            $(document).on('click', '.car-upload-button', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $button = $(this);
                var $container = $button.closest('.car-file-upload-container');
                var $input = $container.find('.car-file-input');
                var $preview = $container.find('.car-file-preview');
                var maxFiles = $button.data('max') || 1;
                var currentFiles = $preview.children().length;
                
                if (currentFiles >= maxFiles && maxFiles > 0) {
                    alert('Maximum images reached');
                    return;
                }
                
                var frame = wp.media({
                    title: 'Select or Upload Image',
                    button: { text: 'Use this image' },
                    library: { type: 'image' },
                    multiple: maxFiles > 1
                });
                
                frame.on('select', function() {
                    var attachments = frame.state().get('selection').toJSON();
                    
                    if (maxFiles === 1) {
                        $preview.empty();
                        $input.val('');
                    }
                    
                    $.each(attachments, function(i, attachment) {
                        if (attachment.type === 'image') {
                            var fileHtml = '<div class="car-file-item" data-attachment-id="' + attachment.id + '">' +
                                '<img src="' + attachment.url + '" class="car-image-preview">' +
                                '<div class="car-file-name">' + attachment.filename + '</div>' +
                                '<button type="button" class="car-remove-file dashicons dashicons-no-alt" title="Remove"></button>' +
                                '</div>';
                            
                            $preview.append(fileHtml);
                            
                            if (maxFiles === 1) {
                                $input.val(attachment.id);
                            } else {
                                var currentValue = $input.val();
                                if (currentValue) {
                                    var values = currentValue.split(',');
                                    values.push(attachment.id);
                                    $input.val(values.join(','));
                                } else {
                                    $input.val(attachment.id);
                                }
                            }
                        }
                    });
                    
                    frame.close();
                });
                
                frame.on('close', function() {
                    frame.detach();
                });
                
                frame.open();
            });
            
            // Remove file event
            $(document).on('click', '.car-remove-file', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $removeBtn = $(this);
                var $fileItem = $removeBtn.closest('.car-file-item');
                var $container = $fileItem.closest('.car-file-upload-container');
                var $input = $container.find('.car-file-input');
                
                if ($input.hasClass('multiple-images')) {
                    // Handle multiple images
                    var attachmentId = $fileItem.data('attachment-id');
                    var currentValue = $input.val();
                    if (currentValue) {
                        var values = currentValue.split(',');
                        var index = values.indexOf(attachmentId.toString());
                        if (index > -1) {
                            values.splice(index, 1);
                            $input.val(values.join(','));
                        }
                    }
                } else {
                    $input.val('');
                }
                
                $fileItem.remove();
            });
        }
        
        // ==============================================
        // LEVEL 1 NESTED REPEATER FUNCTIONS
        // ==============================================
        
        function initNestedRepeaters() {
            console.log('[Custom Advance Repeater] Initializing level 1 repeaters...');
            
            // Helper function to get proper row count
            function getRowCount($tbody) {
                return $tbody.find('tr[data-nested-index]').not('.car-clone-nested-row').length;
            }
            
            // Helper function to update row indices
            function updateRowIndices($tbody, skipFirstRow = false) {
                var rows = $tbody.find('tr[data-nested-index]').not('.car-clone-nested-row');
                var startIndex = skipFirstRow ? 1 : 0;
                
                rows.each(function(newIndex) {
                    if (skipFirstRow && newIndex === 0) return; // Skip first row if needed
                    
                    var $row = $(this);
                    var oldIndex = parseInt($row.data('nested-index'));
                    
                    if (oldIndex !== newIndex) {
                        console.log('[Custom Advance Repeater] Reindexing row from', oldIndex, 'to', newIndex);
                        
                        // Update data attribute
                        $row.data('nested-index', newIndex);
                        $row.attr('data-nested-index', newIndex);
                        
                        // Update display index
                        var $displaySpan = $row.find('.nested-row-index');
                        if ($displaySpan.length === 0) {
                            $displaySpan = $('<span>').addClass('nested-row-index');
                            $row.find('.car-row-handle').append($displaySpan);
                        }
                        $displaySpan.text(newIndex + 1);
                        
                        // Update all name attributes with new index
                        $row.find('[name]').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                // Replace numeric indices in the 4th position (parts[3])
                                var parts = name.split('[');
                                if (parts.length >= 5) {
                                    // Check if parts[3] is a number (not a placeholder)
                                    var currentPart = parts[3].replace(']', '');
                                    if (!isNaN(currentPart)) {
                                        parts[3] = newIndex + ']';
                                        var newName = parts.join('[');
                                        $(this).attr('name', newName);
                                    }
                                }
                            }
                        });
                    }
                });
            }
            
            // Fix existing rows on initialization
            function fixExistingRows() {
                $('.car-nested-tbody').each(function() {
                    var $tbody = $(this);
                    updateRowIndices($tbody);
                });
            }
            
            // Run fix on page load
            fixExistingRows();
            
            // Add level 1 nested row - using event delegation with namespace
            $(document).off('click.carAdd').on('click.carAdd', '.car-add-nested-row', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('[Custom Advance Repeater] Adding new level 1 row...');
                
                var $button = $(this);
                var $nestedRepeater = $button.closest('.car-nested-repeater');
                var $table = $nestedRepeater.find('.car-nested-table');
                var $tbody = $table.find('tbody.car-nested-tbody');
                var $cloneRow = $tbody.find('.car-clone-nested-row');
                
                if ($cloneRow.length === 0) {
                    console.error('Custom Advance Repeater: Clone row not found');
                    return;
                }
                
                // Get proper row count
                var nextIndex = getRowCount($tbody);
                
                console.log('[Custom Advance Repeater] Current row count:', nextIndex, 'New index:', nextIndex);
                
                // Clone the row
                var $newRow = $cloneRow.clone();
                $newRow.removeClass('car-clone-nested-row').show();
                
                // Update all placeholders with new index
                $newRow.find('[name]').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        // Replace both placeholder formats
                        name = name.replace(/\[__NESTED_INDEX__\]/g, '[' + nextIndex + ']');
                        name = name.replace(/\$__NESTED_INDEX__\$/g, '[' + nextIndex + ']');
                        $(this).attr('name', name);
                    }
                });
                
                // Update IDs
                $newRow.find('[id]').each(function() {
                    var id = $(this).attr('id');
                    if (id) {
                        id = id.replace(/__NESTED_INDEX__/g, nextIndex);
                        $(this).attr('id', id);
                    }
                });
                
                // Set data attribute and display
                $newRow.attr('data-nested-index', nextIndex);
                
                // Ensure display span exists
                var $displaySpan = $newRow.find('.nested-row-index');
                if ($displaySpan.length === 0) {
                    $displaySpan = $('<span>').addClass('nested-row-index');
                    $newRow.find('.car-row-handle').append($displaySpan);
                }
                $displaySpan.text(nextIndex + 1);
                
                // Insert before clone row
                $cloneRow.before($newRow);
                
                console.log('[Custom Advance Repeater] Added row at index', nextIndex);
                
                // Reinitialize field controls - including datepicker
                setTimeout(function() {
                    initFieldControls($newRow);
                }, 100);
            });
            
            // Remove level 1 nested row - using event delegation with namespace
            $(document).off('click.carRemove').on('click.carRemove', '.car-remove-nested-row', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Prevent multiple clicks
                if ($(this).data('processing')) return;
                $(this).data('processing', true);
                
                console.log('[Custom Advance Repeater] Removing level 1 row...');
                
                var $button = $(this);
                var $row = $button.closest('tr');
                var $tbody = $row.closest('tbody');
                var removedIndex = parseInt($row.data('nested-index'));
                
                // Store reference for cleanup
                var rowData = {
                    index: removedIndex,
                    tbody: $tbody
                };
                
                // Single confirmation check
                if (window.CAR_ConfirmRemove === undefined) {
                    window.CAR_ConfirmRemove = true;
                    
                    if (confirm('Are you sure you want to remove this row?')) {
                        console.log('[Custom Advance Repeater] Removing row at index', removedIndex);
                        
                        // Remove the row
                        $row.remove();
                        
                        // Reindex remaining rows
                        updateRowIndices($tbody);
                        
                        console.log('[Custom Advance Repeater] Reindexing complete');
                    }
                    
                    setTimeout(function() {
                        window.CAR_ConfirmRemove = undefined;
                    }, 100);
                }
                
                $(this).data('processing', false);
            });
            
            // Sortable for level 1 - Initialize only once
            if (typeof $.fn.sortable === 'function') {
                $('.car-nested-tbody').each(function() {
                    var $tbody = $(this);
                    
                    // Check if already sortable
                    if (!$tbody.hasClass('ui-sortable')) {
                        $tbody.sortable({
                            handle: '.car-row-handle',
                            axis: 'y',
                            placeholder: 'car-sortable-placeholder',
                            forcePlaceholderSize: true,
                            items: 'tr[data-nested-index]', // Only sort level 1 rows
                            start: function(e, ui) {
                                ui.placeholder.height(ui.item.height());
                                console.log('[Custom Advance Repeater] Started sorting level 1 rows');
                            },
                            update: function(event, ui) {
                                console.log('[Custom Advance Repeater] Sorting complete, reindexing...');
                                var $tbody = $(this);
                                updateRowIndices($tbody);
                                console.log('[Custom Advance Repeater] Sorting reindex complete');
                            }
                        });
                    }
                });
            }
            
            console.log('[Custom Advance Repeater] Level 1 repeaters initialized');
        }

        // Helper function to initialize field controls
        function initFieldControls($row) {
            // Datepickers - FIXED with proper calendar settings
            $row.find('.car-datepicker').each(function() {
                if (typeof $.fn.datepicker === 'function' && !$(this).hasClass('hasDatepicker')) {
                    $(this).datepicker({
                        dateFormat: 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true,
                        yearRange: '-100:+10',
                        showButtonPanel: true,
                        showOtherMonths: true,
                        selectOtherMonths: true,
                        beforeShow: function(input, inst) {
                            setTimeout(function() {
                                if (inst.dpDiv) {
                                    inst.dpDiv.css({
                                        'z-index': '100001',
                                        'font-size': '13px',
                                        'line-height': '1.4',
                                        'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
                                        'width': '300px'
                                    });
                                    
                                    // Fix for nested tables
                                    if ($(input).closest('.car-nested-table').length) {
                                        inst.dpDiv.css({
                                            'font-size': '12px',
                                            'width': '280px'
                                        });
                                    }
                                }
                            }, 0);
                            return {};
                        }
                    }).addClass('hasDatepicker');
                }
            });
            
            // Color pickers
            $row.find('.car-colorpicker').each(function() {
                if (typeof $.fn.wpColorPicker === 'function' && !$(this).hasClass('wp-color-picker')) {
                    $(this).wpColorPicker().addClass('wp-color-picker');
                }
            });
            
            // Add widefat class to form elements
            $row.find('input[type="text"], textarea, select').addClass('widefat');
        }

        // Initialize on document ready
        $(document).ready(function() {
            console.log('[Custom Advance Repeater] Document ready, initializing...');
            
            // Clear any existing Custom Advance Repeater event handlers to prevent duplicates
            $(document).off('click.carAdd');
            $(document).off('click.carRemove');
            
            // Initialize nested repeaters
            initNestedRepeaters();
            
            console.log('[Custom Advance Repeater] Initialization complete');
        });
        
        // ==============================================
        // LEVEL 2 NESTED REPEATER FUNCTIONS
        // ==============================================

        function initNested2Repeaters() {
            // Add level 2 nested row
            $(document).on('click', '.car-add-nested2-row', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $button = $(this);
                var $nested2Repeater = $button.closest('.car-nested-repeater');
                var $table = $nested2Repeater.find('.car-nested-table');
                var $tbody = $table.find('tbody.car-nested-tbody');
                var $cloneRow = $tbody.find('.car-clone-nested2-row');
                
                if ($cloneRow.length === 0) {
                    console.error('Nested2 repeater clone row not found');
                    return;
                }
                
                // FIXED: Use max existing index + 1 to ensure unique, sequential indices
                var maxIndex = -1;
                $tbody.find('tr:not(.car-clone-nested2-row)').each(function() {
                    var idx = parseInt($(this).data('nested2-index'));
                    if (!isNaN(idx) && idx > maxIndex) maxIndex = idx;
                });
                var nested2RowCount = maxIndex + 1;
                console.log('Adding new row at index: ' + nested2RowCount);
                
                var $newRow = $cloneRow.clone();
                $newRow.removeClass('car-clone-nested2-row').show();
                
                // FIXED: Use parts-based replacement for consistency and reliability
                $newRow.find('[name]').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        var parts = name.split('[');
                        if (parts.length >= 6) { // Nested2 names have at least 7 parts
                            parts[5] = nested2RowCount + ']'; // parts[5] is the nested2_index]
                            name = parts.join('[');
                        }
                        $(this).attr('name', name);
                        console.log('Updated name: ' + name);
                    }
                });
                
                $newRow.find('[id]').each(function() {
                    var id = $(this).attr('id');
                    if (id) {
                        id = id.replace(/__NESTED2_INDEX__/g, nested2RowCount);
                        $(this).attr('id', id);
                        console.log('Updated id: ' + id);
                    }
                });
                
                $newRow.attr('data-nested2-index', nested2RowCount);
                $newRow.find('.nested2-row-index').text(nested2RowCount + 1);
                
                // Insert before the clone row
                $cloneRow.before($newRow);
                
                // Reinitialize all field controls for the new row - including datepicker fix
                setTimeout(function() {
                    // Datepickers with proper calendar
                    $newRow.find('.car-datepicker').each(function() {
                        if (typeof $.fn.datepicker === 'function' && !$(this).hasClass('hasDatepicker')) {
                            $(this).datepicker({
                                dateFormat: 'yy-mm-dd',
                                changeMonth: true,
                                changeYear: true,
                                yearRange: '-100:+10',
                                showButtonPanel: true,
                                showOtherMonths: true,
                                selectOtherMonths: true,
                                beforeShow: function(input, inst) {
                                    setTimeout(function() {
                                        if (inst.dpDiv) {
                                            inst.dpDiv.css({
                                                'z-index': '100001',
                                                'font-size': '12px',
                                                'line-height': '1.4',
                                                'width': '280px'
                                            });
                                        }
                                    }, 0);
                                    return {};
                                }
                            }).addClass('hasDatepicker');
                        }
                    });
                    
                    // Color pickers
                    $newRow.find('.car-colorpicker').each(function() {
                        if (typeof $.fn.wpColorPicker === 'function' && !$(this).hasClass('wp-color-picker')) {
                            $(this).wpColorPicker().addClass('wp-color-picker');
                        }
                    });
                    
                    // Make sure all inputs have proper styling
                    $newRow.find('input[type="text"], textarea, select').addClass('widefat');
                    
                }, 100);
            });
            
            // Remove level 2 nested row - FIXED: Force full reindexing to 0,1,2,... and use reliable parts-based targeting
            $(document).on('click', '.car-remove-nested2-row', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if ($(this).data('processing')) return;
                $(this).data('processing', true);
                
                if (confirm('Are you sure you want to remove this nested row?')) {
                    var $row = $(this).closest('tr');
                    var $tbody = $row.closest('tbody');
                    
                    $row.remove();
                    
                    // Force reindexing of all remaining rows to sequential 0,1,2,... (remove the oldIndex check to ensure it always happens)
                    $tbody.find('tr:not(.car-clone-nested2-row)').each(function(newIndex) {
                        var $currentRow = $(this);
                        
                        // Always update to ensure sequential indices
                        $currentRow.data('nested2-index', newIndex);
                        $currentRow.attr('data-nested2-index', newIndex);
                        $currentRow.find('.nested2-row-index').text(newIndex + 1);
                        
                        // Update all input names in this row - use parts-based targeting for reliability
                        $currentRow.find('[name]').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                var parts = name.split('[');
                                if (parts.length >= 6) { // Nested2 names have at least 7 parts
                                    parts[5] = newIndex + ']'; // parts[5] is the nested2_index]
                                    name = parts.join('[');
                                }
                                $(this).attr('name', name);
                                console.log('Updated name: ' + name);
                            }
                        });
                        
                        // Update IDs too
                        $currentRow.find('[id]').each(function() {
                            var id = $(this).attr('id');
                            if (id && id.includes('__NESTED2_INDEX__')) {
                                var newId = id.replace(/__NESTED2_INDEX__/g, newIndex);
                                $(this).attr('id', newId);
                                console.log('Updated id: ' + id);
                            }
                        });
                    });
                }
                
                $(this).data('processing', false);
            });
            
            // Sortable for level 2 - FIXED: Same force reindexing logic
            if (typeof $.fn.sortable === 'function') {
                $('.car-nested-tbody').sortable({
                    handle: '.car-row-handle',
                    axis: 'y',
                    placeholder: 'car-sortable-placeholder',
                    forcePlaceholderSize: true,
                    start: function(e, ui) {
                        ui.placeholder.height(ui.item.height());
                    },
                    update: function(event, ui) {
                        var $tbody = $(this);
                        // Force reindexing after sorting
                        $tbody.find('tr:not(.car-clone-nested2-row)').each(function(newIndex) {
                            var $currentRow = $(this);
                            
                            $currentRow.data('nested2-index', newIndex);
                            $currentRow.attr('data-nested2-index', newIndex);
                            $currentRow.find('.nested2-row-index').text(newIndex + 1);
                            
                            // Update all input names in this row - same parts-based targeting
                            $currentRow.find('[name]').each(function() {
                                var name = $(this).attr('name');
                                if (name) {
                                    var parts = name.split('[');
                                    if (parts.length >= 6) {
                                        parts[5] = newIndex + ']';
                                        name = parts.join('[');
                                    }
                                    $(this).attr('name', name);
                                }
                            });
                        });
                    }
                });
            }
            
            carLog('Level 2 nested repeaters initialized');
        }

        // ==============================================
        // MAIN INITIALIZATION FUNCTION
        // ==============================================
        
        function initCAR() {
            if ($('body').hasClass('car-initialized')) {
                carLog('Custom Advance Repeater already initialized, skipping');
                return;
            }
            
            carLog('Starting Custom Advance Repeater initialization');
            
            waitForJQueryUI(function(success) {
                if (success) {
                    initDatepicker();
                    initColorpicker();
                    initImageUpload();
                    initNestedRepeaters();
                    initNested2Repeaters();
                    
                    $('body').addClass('car-initialized');
                    
                    carLog('Custom Advance Repeater initialization complete');
                } else {
                    carLog('Custom Advance Repeater initialization failed - jQuery UI not loaded');
                    initImageUpload();
                    initNestedRepeaters();
                    initNested2Repeaters();
                    $('body').addClass('car-initialized');
                }
            });
        }
        
        // Initialize everything
        initCAR();
        
        // Reinitialize when new content is added via AJAX
        $(document).ajaxComplete(function() {
            if (!$('body').hasClass('car-initialized')) {
                initCAR();
            }
        });
        
    })(jQuery);
});