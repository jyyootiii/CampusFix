/**
 * CampusFix — Frontend Helper Scripts
 * Simple Vanilla JavaScript for table filtering, image previews, and alerts
 */

document.addEventListener('DOMContentLoaded', function () {

    // 1. Table Live Search & Filter
    const searchInput = document.getElementById('tableSearch');
    const categoryFilter = document.getElementById('filterCategory');
    const statusFilter = document.getElementById('filterStatus');
    const priorityFilter = document.getElementById('filterPriority');
    const targetTable = document.querySelector('table.grid-table tbody');

    if (targetTable && (searchInput || categoryFilter || statusFilter || priorityFilter)) {
        function applyFilters() {
            const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const selectedCat = categoryFilter ? categoryFilter.value : '';
            const selectedStatus = statusFilter ? statusFilter.value : '';
            const selectedPrio = priorityFilter ? priorityFilter.value : '';

            const rows = targetTable.querySelectorAll('tr');

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const rowCat = row.getAttribute('data-category') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const rowPrio = row.getAttribute('data-priority') || '';

                const matchesQuery = !query || text.includes(query);
                const matchesCat = !selectedCat || rowCat === selectedCat;
                const matchesStatus = !selectedStatus || rowStatus === selectedStatus;
                const matchesPrio = !selectedPrio || rowPrio === selectedPrio;

                if (matchesQuery && matchesCat && matchesStatus && matchesPrio) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
        if (priorityFilter) priorityFilter.addEventListener('change', applyFilters);
    }

    // 2. Image Upload Preview
    const imageInput = document.getElementById('complaintImage');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const previewImage = document.getElementById('imagePreview');

    if (imageInput && previewImage && previewContainer) {
        imageInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    alert('Please select a valid image file (JPG, PNG, WEBP).');
                    this.value = '';
                    previewContainer.style.display = 'none';
                    return;
                }
                if (file.size > 3 * 1024 * 1024) {
                    alert('File size exceeds 3MB limit.');
                    this.value = '';
                    previewContainer.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
            }
        });
    }

    // 3. Simple URL Query Alert Reader for Static HTML Pages
    const urlParams = new URLSearchParams(window.location.search);
    const alertBox = document.getElementById('statusAlert');

    if (alertBox) {
        if (urlParams.has('registered')) {
            alertBox.className = 'alert alert-success';
            alertBox.textContent = 'Registration successful! Please log in with your credentials.';
            alertBox.style.display = 'block';
        } else if (urlParams.has('loggedout')) {
            alertBox.className = 'alert alert-info';
            alertBox.textContent = 'You have been logged out successfully.';
            alertBox.style.display = 'block';
        } else if (urlParams.has('sent')) {
            alertBox.className = 'alert alert-success';
            alertBox.textContent = 'Thank you! Your message has been sent to the Estate Office.';
            alertBox.style.display = 'block';
        } else if (urlParams.has('error')) {
            alertBox.className = 'alert alert-error';
            const err = urlParams.get('error');
            if (err === 'invalid') {
                alertBox.textContent = 'Invalid email or password. Please try again.';
            } else if (err === 'duplicate') {
                alertBox.textContent = 'An account with that Email or Student ID already exists.';
            } else if (err === 'mismatch') {
                alertBox.textContent = 'Passwords do not match.';
            } else if (err === 'short') {
                alertBox.textContent = 'Password must be at least 8 characters long.';
            } else if (err === 'required') {
                alertBox.textContent = 'Please fill in all required fields.';
            } else {
                alertBox.textContent = 'An error occurred. Please check your entries and try again.';
            }
            alertBox.style.display = 'block';
        }
    }

    // 4. Synchronize Navbar Session for Static HTML Pages (about.html, home.html, contact.html, knowledge.html)
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
        return null;
    }

    const userRole = getCookie('cf_user_role');
    const userName = getCookie('cf_user_name');
    const navLinks = document.querySelector('.site-nav .nav-links');
    const userNavStatus = document.querySelector('.site-nav .user-nav-status');

    // 5. Prevent logged-in user from seeing or accessing login.html and register.html
    const currentFile = window.location.pathname.split('/').pop().toLowerCase();
    if (userRole && (currentFile === 'login.html' || currentFile === 'register.html')) {
        if (userRole === 'Admin') {
            window.location.replace('admin/dashboard.php');
            return;
        } else if (userRole === 'Student') {
            window.location.replace('student/dashboard.php');
            return;
        }
    }

    // 6. Update home.html hero call-to-action buttons if user is already logged in
    const homeHeroButtons = document.getElementById('homeHeroButtons');
    if (homeHeroButtons && userRole) {
        if (userRole === 'Student') {
            homeHeroButtons.innerHTML = `
                <a href="student/dashboard.php" class="btn btn-primary" style="padding: 12px 28px; font-size: 16px;">Go to Student Dashboard</a>
                <a href="student/submit_complaint.php" class="btn btn-secondary" style="padding: 12px 28px; font-size: 16px;">Lodge New Complaint</a>
                <a href="student/track_complaint.php" class="btn btn-secondary" style="padding: 12px 28px; font-size: 16px;">Track Existing Complaint</a>
            `;
        } else if (userRole === 'Admin') {
            homeHeroButtons.innerHTML = `
                <a href="admin/dashboard.php" class="btn btn-primary" style="padding: 12px 28px; font-size: 16px;">Go to Admin Dashboard</a>
                <a href="admin/manage_complaints.php" class="btn btn-secondary" style="padding: 12px 28px; font-size: 16px;">Manage Complaints</a>
                <a href="admin/reports.php" class="btn btn-secondary" style="padding: 12px 28px; font-size: 16px;">View Analytics</a>
            `;
        }
    }

    // Only apply client-side sync on static HTML pages (where server PHP role-tag is not already rendered)
    if (userNavStatus && !userNavStatus.querySelector('.role-tag')) {
        const path = window.location.pathname;
        const isInSubdir = path.includes('/student/') || path.includes('/admin/');
        const base = isInSubdir ? '../' : '';

        if (userRole === 'Admin') {
            userNavStatus.innerHTML = `
                <span>Hello, <strong>${userName || 'Campus Administrator'}</strong> <span class="role-tag admin">Admin</span></span>
                <a href="${base}logout.php" class="btn btn-secondary btn-sm">Logout</a>
            `;
            if (navLinks) {
                navLinks.innerHTML = `
                    <li><a href="${base}home.html">Home</a></li>
                    <li><a href="${base}admin/dashboard.php">Admin Dashboard</a></li>
                    <li><a href="${base}admin/manage_complaints.php">Manage Complaints</a></li>
                    <li><a href="${base}admin/reports.php">Reports &amp; Analytics</a></li>
                    <li><a href="${base}view_data.php">Database Viewer</a></li>
                    <li><a href="${base}about.html" class="${path.includes('about') ? 'active' : ''}">About Us</a></li>
                    <li><a href="${base}knowledge.html" class="${path.includes('knowledge') ? 'active' : ''}">Knowledge Centre</a></li>
                    <li><a href="${base}contact.html" class="${path.includes('contact') ? 'active' : ''}">Contact Us</a></li>
                `;
            }
        } else if (userRole === 'Student') {
            userNavStatus.innerHTML = `
                <span>Hello, <strong>${userName || 'Student'}</strong> <span class="role-tag">Student</span></span>
                <a href="${base}logout.php" class="btn btn-secondary btn-sm">Logout</a>
            `;
            if (navLinks) {
                navLinks.innerHTML = `
                    <li><a href="${base}home.html">Home</a></li>
                    <li><a href="${base}student/dashboard.php">Dashboard</a></li>
                    <li><a href="${base}student/submit_complaint.php">Submit Complaint</a></li>
                    <li><a href="${base}student/complaint_history.php">My Complaints</a></li>
                    <li><a href="${base}student/track_complaint.php">Track Complaint</a></li>
                    <li><a href="${base}about.html" class="${path.includes('about') ? 'active' : ''}">About Us</a></li>
                    <li><a href="${base}knowledge.html" class="${path.includes('knowledge') ? 'active' : ''}">Knowledge Centre</a></li>
                    <li><a href="${base}contact.html" class="${path.includes('contact') ? 'active' : ''}">Contact Us</a></li>
                `;
            }
        }
    }
});
