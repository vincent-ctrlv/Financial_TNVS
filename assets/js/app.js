(function(){
	'use strict';

	function qs(sel, root){ return (root||document).querySelector(sel); }
	function qsa(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }

	var app = qs('.app');
	var sidebar = qs('#sidebar');
	var overlay = qs('#overlay');
	var toggleBtn = qs('#menuToggle');

	function isMobile(){ return window.matchMedia('(max-width: 900px)').matches; }

	function openMobileSidebar(){
		if (!sidebar) return;
		sidebar.classList.add('open');
		if (overlay) overlay.classList.add('show');
		if (toggleBtn) toggleBtn.classList.add('sidebar-open');
	}

	function closeMobileSidebar(){
		if (!sidebar) return;
		sidebar.classList.remove('open');
		if (overlay) overlay.classList.remove('show');
		if (toggleBtn) toggleBtn.classList.remove('sidebar-open');
	}

	function toggleDesktopSidebar(){
		if (!app) return;
		var collapsed = app.classList.toggle('sidebar-collapsed');
		if (toggleBtn) toggleBtn.classList.toggle('sidebar-open', collapsed);
	}

	function handleToggle(){
		if (isMobile()) {
			if (sidebar.classList.contains('open')) closeMobileSidebar();
			else openMobileSidebar();
		} else {
			toggleDesktopSidebar();
		}
	}

	function handleOverlayClick(){
		if (isMobile()) closeMobileSidebar();
	}

	function handleNavClick(){
		if (isMobile()) closeMobileSidebar();
	}

	function handleResize(){
		// Ensure proper state when switching breakpoints
		if (!isMobile()) {
			closeMobileSidebar();
		} else {
			// keep desktop collapsed state but no visual when mobile
		}
	}

	// Bind events
	if (toggleBtn) toggleBtn.addEventListener('click', handleToggle);
	if (overlay) overlay.addEventListener('click', handleOverlayClick);
	qsa('.sidebar .nav').forEach(function(a){
		a.addEventListener('click', handleNavClick);
	});
	window.addEventListener('resize', handleResize);

	// Improve focus outline for accessibility
	qsa('a.nav').forEach(function(a){
		a.addEventListener('focus', function(){ a.classList.add('focus'); });
		a.addEventListener('blur', function(){ a.classList.remove('focus'); });
	});

	// (Profile link injection removed by request)

	// Enhance header user block with avatar dropdown if available (disabled)
	try {
		// disabled per request
	} catch(e){}
	// --- User Management UI Logic ---


	window.hideUserModal = function(){
		if (qs('#userModal')) qs('#userModal').classList.remove('show');
	};

	window.addUser = function(){
		if (!qs('#userModal')) return;
		qs('#modalTitle').textContent = 'Add New User';
		qs('#formUserId').value = '';
		qs('#formName').value = '';
		qs('#formEmail').value = '';
		qs('#formRole').value = 'driver';
		qs('#formPassword').required = true;
		qs('#formPassword').value = '';
		qs('#userModal').classList.add('show');
	};

	window.editUser = function(u){
		if (!qs('#userModal')) return;
		qs('#modalTitle').textContent = 'Edit User: ' + u.name;
		qs('#formUserId').value = u.user_id;
		qs('#formName').value = u.name;
		qs('#formEmail').value = u.email;
		qs('#formRole').value = u.role;
		qs('#formPassword').required = false;
		qs('#formPassword').value = '';
		qs('#userModal').classList.add('show');
	};

	window.saveUser = function(e){
		e.preventDefault();
		var form = e.target;
		var formData = new FormData(form);
		
		fetch(window._userApiUrl || 'user_api.php', {
			method: 'POST',
			body: formData
		})
		.then(res => res.json())
		.then(data => {
			if (data.success) {
				location.reload(); // Simple reload to refresh table
			} else {
				alert(data.error || 'Failed to save user');
			}
		})
		.catch(err => {
			console.error(err);
			alert('Connection error');
		});
	};

	window.deleteUser = function(id){
		if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
		
		var formData = new FormData();
		formData.append('action', 'delete');
		formData.append('user_id', id);
		formData.append('_csrf', qs('input[name="_csrf"]').value);

		fetch(window._userApiUrl || 'user_api.php', {
			method: 'POST',
			body: formData
		})
		.then(res => res.json())
		.then(data => {
			if (data.success) {
				location.reload();
			} else {
				alert(data.error || 'Failed to delete user');
			}
		})
		.catch(err => {
			console.error(err);
			alert('Connection error');
		});
	};

	window.filterUsers = function(){
		var q = qs('#userSearch').value.toLowerCase();
		var rows = qsa('#usersTable tbody tr');
		rows.forEach(row => {
			var text = (row.dataset.name + ' ' + row.dataset.email + ' ' + row.dataset.role).toLowerCase();
			row.style.display = text.includes(q) ? '' : 'none';
		});
	};
})();
