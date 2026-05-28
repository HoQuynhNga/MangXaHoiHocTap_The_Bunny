// Toggle Sidebar on Mobile
function toggleSidebar() {
  const sidebar = document.getElementById('sidebarLeft');
  const overlay = document.getElementById('mobileOverlay');
  sidebar.classList.toggle('open');
  overlay.classList.toggle('show');
}
