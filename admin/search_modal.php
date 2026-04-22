<?php
// Shared Search Modal - Include this in all admin pages
$autoOpenModal = isset($_GET['open_search']) && $_GET['open_search'] === 'modal';
?>
<style>
/* Search Modal Styles */
.search-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.search-modal-overlay.active {
    display: flex;
}

.search-modal-content {
    background: white;
    padding: 30px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    position: relative;
}

.search-modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.search-modal-close:hover {
    color: #333;
}

.search-modal-header {
    text-align: center;
    margin-bottom: 20px;
}

.search-modal-header h2 {
    margin: 0;
    color: #333;
}

.search-input-group {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-input-group input {
    flex: 1;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}

.search-input-group input:focus {
    outline: none;
    border-color: #007bff;
}

.search-input-group button {
    padding: 12px 20px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
}

.search-input-group button:hover {
    background: #0056b3;
}

.search-student-info-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 15px;
}

.search-student-info-card h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.search-info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.search-info-row:last-child {
    border-bottom: none;
}

.search-info-label {
    font-weight: bold;
    color: #555;
}

.search-info-value {
    color: #333;
}

.search-sessions-badge {
    display: inline-block;
    background: #28a745;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
}

.search-sessions-badge.low {
    background: #dc3545;
}

.search-sessions-badge.medium {
    background: #ffc107;
    color: #333;
}

.search-no-result {
    text-align: center;
    padding: 30px;
    color: #666;
}

.search-no-result .icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.search-modal-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.search-modal-actions a {
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    text-align: center;
}

.search-btn-sitin {
    background: #007bff;
    color: white;
}

.search-btn-records {
    background: #6c757d;
    color: white;
}

.search-loading {
    text-align: center;
    padding: 30px;
}

.search-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: searchSpin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes searchSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.search-toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #dc3545;
    color: white;
    padding: 15px 25px;
    border-radius: 5px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    z-index: 2000;
    display: none;
    animation: searchSlideIn 0.3s ease;
}

.search-toast.show {
    display: block;
}

@keyframes searchSlideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

<!-- Search Student Modal -->
<div class="search-modal-overlay" id="searchModal">
    <div class="search-modal-content">
        <span class="search-modal-close" onclick="closeSearchModal()">&times;</span>
        <div class="search-modal-header">
            <h2>Search Student</h2>
        </div>
        
        <div class="search-input-group">
            <input type="text" id="studentSearchInput" placeholder="Enter ID Number or Name" autocomplete="off">
            <button onclick="searchStudent()">Search</button>
        </div>

        <div id="searchResults"></div>
    </div>
</div>

<!-- Toast Notification -->
<div class="search-toast" id="searchToast">Student not found in the system</div>

<script>
function openSearchModal() {
    document.getElementById('searchModal').classList.add('active');
    document.getElementById('studentSearchInput').focus();
    document.getElementById('searchResults').innerHTML = '';
}

function closeSearchModal() {
    document.getElementById('searchModal').classList.remove('active');
    document.getElementById('studentSearchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
}

async function searchStudent() {
    var query = document.getElementById('studentSearchInput').value.trim();
    var resultsContainer = document.getElementById('searchResults');
    
    if (!query) {
        return;
    }

    resultsContainer.innerHTML = '<div class="search-loading"><div class="search-spinner"></div><p>Searching...</p></div>';

    try {
        var response = await fetch('api_search_student.php?q=' + encodeURIComponent(query));
        var data = await response.json();

        if (data.error) {
            resultsContainer.innerHTML = '<div class="search-no-result"><p>Error: ' + data.error + '</p></div>';
            return;
        }

        if (data.found) {
            var student = data.student;
            var fullName = student.firstname + ' ' + (student.middlename ? student.middlename + ' ' : '') + student.lastname;
            
            var sessionClass = '';
            if (student.remaining_sessions <= 5) {
                sessionClass = 'low';
            } else if (student.remaining_sessions <= 15) {
                sessionClass = 'medium';
            }

            var registeredDate = new Date(student.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            resultsContainer.innerHTML = '<div class="search-student-info-card">' +
                '<h3>Student Information</h3>' +
                '<div class="search-info-row"><span class="search-info-label">ID Number:</span><span class="search-info-value">' + student.id_number + '</span></div>' +
                '<div class="search-info-row"><span class="search-info-label">Name:</span><span class="search-info-value">' + fullName + '</span></div>' +
                '<div class="search-info-row"><span class="search-info-label">Course & Level:</span><span class="search-info-value">' + student.course + ' - ' + student.level + '</span></div>' +
                '<div class="search-info-row"><span class="search-info-label">Sessions Left:</span><span class="search-info-value"><span class="search-sessions-badge ' + sessionClass + '">' + student.remaining_sessions + '</span></span></div>' +
                '<div class="search-modal-actions">' +
                '<button class="search-btn-sitin" onclick="showSitinModal(\'' + student.id_number + '\', \'' + escapeHtml(fullName) + '\', ' + student.remaining_sessions + ')">Start Sit-In</button>' +
                '</div></div>';
        } else {
            showSearchToast();
            resultsContainer.innerHTML = '<div class="search-no-result"><p>No student found matching "' + escapeHtml(query) + '"</p></div>';
        }
    } catch (error) {
        resultsContainer.innerHTML = '<div class="search-no-result"><p>Error searching for student</p></div>';
    }
}

function showSearchToast() {
    var toast = document.getElementById('searchToast');
    toast.classList.add('show');
    setTimeout(function() {
        toast.classList.remove('show');
    }, 3000);
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('studentSearchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchStudent();
    }
});

document.getElementById('searchModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSearchModal();
    }
});

<?php if ($autoOpenModal): ?>
window.onload = function() {
    openSearchModal();
};
<?php endif; ?>

// Sit-In Modal Functions
function showSitinModal(idno, name, sessions) {
    // Remove existing sit-in modal if any
    var existingModal = document.getElementById('sitinModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal HTML dynamically with proper modal styling
    var modalHtml = `
    <div id="sitinModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <span onclick="closeSitinModal()" style="position: absolute; top: 10px; right: 20px; font-size: 28px; cursor: pointer; color: #666;">&times;</span>
            <h2 style="margin-top: 0;">Start Sit-In</h2>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">ID Number</label>
                <input type="text" id="sitinIdNumber" value="${idno}" readonly style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; background: #f0f0f0; box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Student Name</label>
                <input type="text" id="sitinStudentName" value="${name}" readonly style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; background: #f0f0f0; box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Purpose *</label>
                <input type="text" id="sitinPurpose" placeholder="Enter purpose" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Laboratory *</label>
                <select id="sitinLab" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box;" onchange="onLabChange()">
                    <option value="">Select Laboratory</option>
                    <option value="524">524</option>
                    <option value="526">526</option>
                    <option value="528">528</option>
                    <option value="530">530</option>
                    <option value="MAC">MAC</option>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <button type="button" id="selectPcBtn" onclick="openPCModal()" style="width: 100%; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;" disabled>Select PC</button>
                <input type="hidden" id="selectedPc" value="">
                <span id="selectedPcDisplay" style="display: block; margin-top: 5px; color: #28a745;"></span>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Remaining Sessions</label>
                <input type="text" id="sitinRemainingSessions" value="${sessions}" readonly style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; background: #f0f0f0; box-sizing: border-box;">
            </div>
            <button onclick="submitSitin()" style="width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Start Sit-In</button>
        </div>
    </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function onLabChange() {
    var lab = document.getElementById('sitinLab').value;
    var pcBtn = document.getElementById('selectPcBtn');
    var pcDisplay = document.getElementById('selectedPcDisplay');
    var selectedPc = document.getElementById('selectedPc');
    
    if (lab) {
        pcBtn.disabled = false;
        pcBtn.style.background = '#144d94';
        // Reset PC selection when lab changes
        selectedPc.value = '';
        pcDisplay.textContent = '';
    } else {
        pcBtn.disabled = true;
        pcBtn.style.background = '#6c757d';
        selectedPc.value = '';
        pcDisplay.textContent = '';
    }
}

function openPCModal() {
    var lab = document.getElementById('sitinLab').value;
    
    // Generate 56 PC numbers (8x7 grid)
    var pcCount = 56;
    
    var pcOptionsHtml = '';
    for (var i = 1; i <= pcCount; i++) {
        pcOptionsHtml += '<div class="pc-option" onclick="selectPC(' + i + ')" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 5px; cursor: pointer; text-align: center; background: white; transition: all 0.2s;" onmouseover="this.style.background=#f0f0f0" onmouseout="this.style.background=white">' + i + '</div>';
    }
    
    var pcModalHtml = `
    <div id="pcSelectModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
            <span onclick="closePCModal()" style="position: absolute; top: 10px; right: 20px; font-size: 28px; cursor: pointer; color: #666;">&times;</span>
            <h2 style="margin-top: 0;">Select PC - Lab ${lab}</h2>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px;">
                ${pcOptionsHtml}
            </div>
        </div>
    </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', pcModalHtml);
}

function selectPC(pcNum) {
    var selectedPc = document.getElementById('selectedPc');
    var pcDisplay = document.getElementById('selectedPcDisplay');
    
    selectedPc.value = pcNum;
    pcDisplay.textContent = 'PC ' + pcNum + ' selected';
    closePCModal();
}

function closePCModal() {
    var modal = document.getElementById('pcSelectModal');
    if (modal) {
        modal.remove();
    }
}

function closeSitinModal() {
    var modal = document.getElementById('sitinModal');
    if (modal) {
        modal.remove();
    }
}

async function submitSitin() {
    var idno = document.getElementById('sitinIdNumber').value;
    var purpose = document.getElementById('sitinPurpose').value.trim();
    var lab = document.getElementById('sitinLab').value;
    var pc = document.getElementById('selectedPc').value;

    if (!purpose || !lab) {
        alert('Please fill in all required fields');
        return;
    }

    try {
        var formData = new FormData();
        formData.append('id_number', idno);
        formData.append('purpose', purpose);
        formData.append('lab', lab);
        if (pc) {
            formData.append('pc_number', pc);
        }

        var response = await fetch('api_start_sitin.php', {
            method: 'POST',
            body: formData
        });
        var data = await response.json();

        if (data.success) {
            alert('Sit-In started successfully!');
            closeSitinModal();
            closeSearchModal();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error starting sit-in');
    }
}
</script>
