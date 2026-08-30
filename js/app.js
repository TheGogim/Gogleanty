// Gogleanty - Google Photos Clone
// Main Application JavaScript - Versión Completa con Álbumes

const API_BASE = 'api/';
let currentMedia = [];
let currentIndex = 0;
let currentView = 'photos';
let currentFilter = {};
let currentAlbum = null;

// Variables para scroll infinito
let currentPage = 1;
let isLoading = false;
let hasMoreMedia = true;
const ITEMS_PER_PAGE = 100; // Cargar 100 fotos a la vez

// DOM Elements
const elements = {
    sidebar: document.getElementById('sidebar'),
    menuToggle: document.getElementById('menuToggle'),
    searchInput: document.getElementById('searchInput'),
    uploadButton: document.getElementById('uploadButton'),
    viewToggle: document.getElementById('viewToggle'),
    fileInput: document.getElementById('fileInput'),
    contentArea: document.getElementById('contentArea'),
    loadingState: document.getElementById('loadingState'),
    emptyState: document.getElementById('emptyState'),
    photosContainer: document.getElementById('photosContainer'),
    mediaModal: document.getElementById('mediaModal'),
    modalOverlay: document.getElementById('modalOverlay'),
    modalClose: document.getElementById('modalClose'),
    mediaDisplay: document.getElementById('mediaDisplay'),
    mediaInfo: document.getElementById('mediaInfo'),
    navPrev: document.getElementById('navPrev'),
    navNext: document.getElementById('navNext'),
    uploadProgress: document.getElementById('uploadProgress'),
    uploadFileName: document.getElementById('uploadFileName'),
    uploadPercent: document.getElementById('uploadPercent'),
    progressFill: document.getElementById('progressFill'),
    storageBar: document.getElementById('storageBar'),
    storageText: document.getElementById('storageText'),
    // Private Album Elements
    passwordModal: document.getElementById('passwordModal'), // Nuevo modal
    passwordInput: document.getElementById('albumPasswordInput'),
    unlockButton: document.getElementById('unlockAlbumBtn'),
    passwordModalClose: document.getElementById('passwordModalClose'),
    // Bulk Selection Elements
    selectModeBtn: document.getElementById('selectModeBtn'),
    bulkActionBar: document.getElementById('bulkActionBar'),
    closeSelectionBtn: document.getElementById('closeSelectionBtn'),
    deleteSelectedBtn: document.getElementById('deleteSelectedBtn'),
    bulkAddToAlbumBtn: document.getElementById('bulkAddToAlbumBtn'),
    selectedCount: document.getElementById('selectedCount'),
    // Add to Album Modal
    addToAlbumModal: document.getElementById('addToAlbumModal'),
    addToAlbumModalOverlay: document.getElementById('addToAlbumModalOverlay'),
    albumSelect: document.getElementById('albumSelect'),
    cancelAddToAlbumBtn: document.getElementById('cancelAddToAlbumBtn'),
    confirmAddToAlbumBtn: document.getElementById('confirmAddToAlbumBtn')
};

// Selection State
let isSelectionMode = false;
let selectedItems = new Set();

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
    initializeEventListeners();
    loadMedia();
    loadStats();
});

// Event Listeners
function initializeEventListeners() {
    // Navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const view = item.dataset.view;
            switchView(view);

            // Close mobile sidebar on selection
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // Menu toggle for mobile
    elements.menuToggle.addEventListener('click', () => {
        toggleSidebar();
    });

    // Sidebar Overlay
    if (document.getElementById('sidebarOverlay')) {
        document.getElementById('sidebarOverlay').addEventListener('click', closeSidebar);
    }

    function toggleSidebar() {
        const isActive = elements.sidebar.classList.contains('active');
        if (isActive) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function openSidebar() {
        elements.sidebar.classList.add('active');
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }

    function displayAlbums(albums) {
        if (albums.length > 0) {
            elements.contentArea.innerHTML = '<div class="albums-grid" id="albumsGrid"></div>';
            const container = document.getElementById('albumsGrid');

            albums.forEach(album => {
                const div = document.createElement('div');
                div.className = 'album-card';

                // Check if private
                const isPrivate = album.type === 'private';
                const coverUrl = isPrivate ? 'assets/lock-icon.png' : (album.cover_url || 'assets/default-album.png'); // Usar icono de candado si es privado

                div.innerHTML = `
                <div class="album-cover" style="position: relative;">
                    ${isPrivate
                        ? `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f0f0f0; font-size:40px;">🔒</div>`
                        : `<img src="${coverUrl}" alt="${album.name}" loading="lazy">`
                    }
                    ${isPrivate ? '<div class="private-badge" style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.6); color:white; padding:4px 8px; border-radius:12px; font-size:12px;">Privado</div>' : ''}
                </div>
                <div class="album-info">
                    <div class="album-name">${album.name}</div>
                    <div class="album-meta">${album.media_count} elementos</div>
                </div>
            `;

                div.addEventListener('click', () => {
                    if (isPrivate) {
                        showPasswordModal(album);
                    } else {
                        openAlbum(album);
                    }
                });

                container.appendChild(div);
            });
        } else {
            elements.contentArea.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📁</div>
                <h3>No hay álbumes</h3>
                <p>Crea un álbum para organizar tus fotos</p>
                <button onclick="showCreateAlbumModal()" class="btn-primary">Crear álbum</button>
            </div>
        `;
        }
    }

    // Private Album Logic
    let pendingPrivateAlbum = null;

    function showPasswordModal(album) {
        pendingPrivateAlbum = album;
        if (elements.passwordInput) elements.passwordInput.value = '';

        // Crear modal dinámicamente si no existe en HTML base (fallback)
        let modal = document.getElementById('passwordModal');
        if (!modal) {
            createPasswordModalHTML();
            modal = document.getElementById('passwordModal');
            // Re-bind listeners
            document.getElementById('unlockAlbumBtn').addEventListener('click', unlockPrivateAlbum);
            document.getElementById('passwordModalClose').addEventListener('click', closePasswordModal);
            document.getElementById('albumPasswordInput').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') unlockPrivateAlbum();
            });
            elements.passwordInput = document.getElementById('albumPasswordInput');
        }

        modal.classList.add('active');
        setTimeout(() => elements.passwordInput && elements.passwordInput.focus(), 100);
    }

    function closePasswordModal() {
        const modal = document.getElementById('passwordModal');
        if (modal) modal.classList.remove('active');
        pendingPrivateAlbum = null;
    }

    function createPasswordModalHTML() {
        const div = document.createElement('div');
        div.id = 'passwordModal';
        div.className = 'modal';
        div.innerHTML = `
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>🔒 Álbum Privado</h3>
                <button class="modal-close" id="passwordModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <p>Este álbum está protegido. Ingresa la contraseña para acceder.</p>
                <div class="form-group">
                    <input type="password" id="albumPasswordInput" class="form-input" placeholder="Contraseña">
                </div>
                <button id="unlockAlbumBtn" class="btn-primary" style="width: 100%;">Desbloquear</button>
            </div>
        </div>
    `;
        document.body.appendChild(div);
    }


    async function unlockPrivateAlbum() {
        if (!pendingPrivateAlbum) return;

        const password = elements.passwordInput ? elements.passwordInput.value : document.getElementById('albumPasswordInput').value;

        if (!password) {
            showNotification('Ingresa la contraseña', 'error');
            return;
        }

        // Intentar abrir el álbum con la contraseña proporcionada
        // openAlbum modificada para aceptar password
        await openAlbum(pendingPrivateAlbum, password);
    }
    function closeSidebar() {
        elements.sidebar.classList.remove('active');
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Search
    let searchTimeout;
    elements.searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchMedia(e.target.value);
        }, 500);
    });

    // Upload
    elements.uploadButton.addEventListener('click', () => {
        elements.fileInput.click();
    });

    elements.fileInput.addEventListener('change', (e) => {
        const files = Array.from(e.target.files);
        if (files.length > 0) {
            uploadFiles(files);
        }
        e.target.value = '';
    });

    // Modal
    elements.modalClose.addEventListener('click', closeModal);
    elements.modalOverlay.addEventListener('click', closeModal);
    elements.navPrev.addEventListener('click', showPreviousMedia);
    elements.navNext.addEventListener('click', showNextMedia);

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (elements.mediaModal.classList.contains('active')) {
            if (e.key === 'Escape') closeModal();
            if (e.key === 'ArrowLeft') showPreviousMedia();
            if (e.key === 'ArrowRight') showNextMedia();
            if (e.key === 'Delete') confirmDelete(currentMedia[currentIndex].id);
        }
    });

    // Drag and drop
    const dropZone = elements.contentArea;

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.background = 'rgba(102, 126, 234, 0.05)';
    });

    // Password Modal Listeners
    if (elements.passwordModalClose) {
        elements.passwordModalClose.addEventListener('click', closePasswordModal);
    }
    if (elements.unlockButton) {
        elements.unlockButton.addEventListener('click', unlockPrivateAlbum);
    }
    if (elements.passwordInput) {
        elements.passwordInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') unlockPrivateAlbum();
        });
    }

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.background = '';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.background = '';
        const files = Array.from(e.dataTransfer.files);
        if (files.length > 0) {
            uploadFiles(files);
        }
    });
}

// Switch View
function switchView(view) {
    currentView = view;
    currentAlbum = null;

    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.view === view) {
            item.classList.add('active');
        }
    });

    currentFilter = {};

    switch (view) {
        case 'photos':
            currentFilter.type = 'image';
            break;
        case 'videos':
            currentFilter.type = 'video';
            break;
        case 'favorites':
            currentFilter.favorite = true;
            break;
        case 'albums':
            loadAlbums();
            return;
    }

    loadMedia();
}

// API Functions
async function apiRequest(endpoint, options = {}) {
    try {
        const response = await fetch(API_BASE + endpoint, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            }
        });

        if (response.status === 401) {
            window.location.href = 'login.html';
            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        // showNotification('Error de conexión. Verifica que XAMPP esté ejecutándose.', 'error'); // Don't show generic error always
        throw error;
    }
}

// Load Media
async function loadMedia(append = false) {
    if (isLoading || (!hasMoreMedia && append)) return;

    isLoading = true;

    if (!append) {
        showLoading();
        currentPage = 1;
        currentMedia = [];
        hasMoreMedia = true;
    }

    try {
        const params = new URLSearchParams();
        params.append('page', currentPage);
        params.append('limit', ITEMS_PER_PAGE);

        if (currentFilter.type) params.append('type', currentFilter.type);
        if (currentFilter.favorite) params.append('favorite', '1');

        const result = await apiRequest(`media?${params.toString()}`);

        if (result.success && result.data) {
            if (append) {
                currentMedia = [...currentMedia, ...result.data];
            } else {
                currentMedia = result.data;
            }

            // Verificar si hay más fotos
            hasMoreMedia = result.data.length === ITEMS_PER_PAGE;

            if (currentMedia.length === 0 && !append) {
                showEmptyState();
            } else {
                // Pass only the new items if appending, or all items if initial load
                displayTimeline(append ? result.data : currentMedia, append);
            }

            currentPage++;
        }
    } catch (error) {
        console.error('Error loading media:', error);
        if (!append) {
            showEmptyState();
        }
    } finally {
        isLoading = false;
    }
}

// Open Album
async function openAlbum(album, password = null) {
    currentAlbum = album;
    currentView = 'album';
    currentPage = 1;
    currentMedia = [];

    // Si se pasa password, cerrar modal
    if (password) {
        closePasswordModal();
    }

    showLoading();

    try {
        // Enviar password en header o query param si existe
        const options = {};
        if (password) {
            options.headers = { 'X-Album-Password': password };
        }

        const url = `albums/${album.id}/media` + (password ? `?password=${encodeURIComponent(password)}` : '');
        const result = await apiRequest(url, options);

        if (result.success && result.data) {
            currentMedia = result.data;

            // UI Update
            elements.contentArea.innerHTML = `
                <div class="album-header">
                    <div class="album-header-content">
                        <button onclick="switchView('albums')" class="back-button">← Volver</button>
                        <div>
                            <h2>${album.name} ${password ? '🔓' : ''}</h2>
                            <p>${album.description || ''}</p>
                            <span class="album-meta">${currentMedia.length} elementos</span>
                        </div>
                    </div>
                    <div class="album-actions">
                        <!-- Botones de acción del álbum -->
                    </div>
                </div>
                <div class="photos-grid" id="albumPhotosGrid"></div>
            `;

            elements.photosContainer = document.getElementById('albumPhotosGrid');
            renderFullTimeline(currentMedia);
        }
    } catch (error) {
        console.error('Error loading album media:', error);
        // Verificar si fue error de contraseña (403)
        if (error.message.includes('403') || error.message.includes('password')) {
            showNotification('Contraseña incorrecta', 'error');
            // Reabrir modal si falló
            showPasswordModal(album);
        } else {
            showNotification('Error al cargar el álbum', 'error');
        }
    } finally {
        hideLoading();
    }
}

// Display Timeline
function displayTimeline(media, append = false) {
    hideLoading();
    elements.emptyState.style.display = 'none';
    elements.photosContainer.style.display = 'block';

    if (!append) {
        // Modo normal: reemplazar todo
        renderFullTimeline(media);
    } else {
        // Modo append: media contiene solo las nuevas fotos
        if (media.length > 0) {
            appendPhotosToTimeline(media);
        }
    }
}

function renderFullTimeline(media) {
    // Group by date
    const grouped = {};
    media.forEach(item => {
        const date = item.date_taken ? item.date_taken.split(' ')[0] : 'Sin fecha';
        if (!grouped[date]) {
            grouped[date] = [];
        }
        grouped[date].push(item);
    });

    // Sort dates descending
    const sortedDates = Object.keys(grouped).sort((a, b) => {
        if (a === 'Sin fecha') return 1;
        if (b === 'Sin fecha') return -1;
        return new Date(b) - new Date(a);
    });

    // Build HTML
    let html = '';
    sortedDates.forEach(date => {
        const items = grouped[date];
        const formattedDate = formatDate(date);

        html += `
            <div class="timeline-section" data-date="${date}">
                <div class="timeline-header">
                    <span class="timeline-date">${formattedDate}</span>
                    <span class="timeline-count">${items.length} ${items.length === 1 ? 'elemento' : 'elementos'}</span>
                </div>
                <div class="photos-grid">
                    ${items.map((item, index) => createPhotoItem(item, currentMedia.indexOf(item))).join('')}
                </div>
            </div>
        `;
    });

    elements.photosContainer.innerHTML = html;
    attachPhotoListeners();
}

function appendPhotosToTimeline(newPhotos) {
    // Agrupar nuevas fotos por fecha
    const grouped = {};
    newPhotos.forEach(item => {
        const date = item.date_taken ? item.date_taken.split(' ')[0] : 'Sin fecha';
        if (!grouped[date]) {
            grouped[date] = [];
        }
        grouped[date].push(item);
    });

    // Para cada fecha, agregar a la sección existente o crear nueva
    Object.keys(grouped).forEach(date => {
        const items = grouped[date];
        const existingSection = document.querySelector(`.timeline-section[data-date="${date}"]`);

        if (existingSection) {
            // Agregar a sección existente
            const grid = existingSection.querySelector('.photos-grid');
            const newHTML = items.map(item => createPhotoItem(item, currentMedia.indexOf(item))).join('');
            grid.innerHTML += newHTML;

            // Actualizar contador
            const allItems = existingSection.querySelectorAll('.photo-item');
            const counter = existingSection.querySelector('.timeline-count');
            counter.textContent = `${allItems.length} ${allItems.length === 1 ? 'elemento' : 'elementos'}`;
        } else {
            // Crear nueva sección
            const formattedDate = formatDate(date);
            const newSection = document.createElement('div');
            newSection.className = 'timeline-section';
            newSection.setAttribute('data-date', date);
            newSection.innerHTML = `
                <div class="timeline-header">
                    <span class="timeline-date">${formattedDate}</span>
                    <span class="timeline-count">${items.length} ${items.length === 1 ? 'elemento' : 'elementos'}</span>
                </div>
                <div class="photos-grid">
                    ${items.map(item => createPhotoItem(item, currentMedia.indexOf(item))).join('')}
                </div>
            `;

            // Insertar en orden cronológico
            const sections = Array.from(document.querySelectorAll('.timeline-section'));
            let inserted = false;

            for (let i = 0; i < sections.length; i++) {
                const sectionDate = sections[i].getAttribute('data-date');
                if (date === 'Sin fecha' || (sectionDate !== 'Sin fecha' && new Date(date) > new Date(sectionDate))) {
                    sections[i].parentNode.insertBefore(newSection, sections[i]);
                    inserted = true;
                    break;
                }
            }

            if (!inserted) {
                elements.photosContainer.appendChild(newSection);
            }
        }
    });

    attachPhotoListeners();
}

function attachPhotoListeners() {
    // Listeners are now handled via inline onclick="handleItemClick(this, event)"
    // This function is kept for compatibility if called elsewhere
}

// Create Photo Item HTML
function createPhotoItem(item, index) {
    const isVideo = item.file_type === 'video' || item.file_type === 'gif';
    const isAudio = item.file_type === 'audio'; // Added for audio files
    const isFavorite = item.is_favorite === '1' || item.is_favorite === 1;
    const isSelected = selectedItems.has(item.id);

    return `
        <div class="photo-item ${isSelected ? 'selected' : ''} ${isSelectionMode ? 'selection-mode' : ''}" data-index="${index}" data-id="${item.id}" onclick="handleItemClick(this, event)">
            <div class="select-checkbox" style="display: ${isSelectionMode ? 'flex' : 'none'}"></div>
            ${item.thumbnail_url ? `
                <img src="${item.thumbnail_url}" alt="${item.original_filename}" loading="lazy">
            ` : `
                <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                    <span style="font-size: 48px;">📄</span>
                </div>
            `}
            ${isVideo ? `
                <div class="video-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    ${item.duration ? formatDuration(item.duration) : 'VIDEO'}
                </div>
            ` : ''}
            ${isAudio ? `
                <div class="audio-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 3v10.55a4 4 0 1 1-4-4H12V3z"/>
                    </svg>
                    ${item.duration ? formatDuration(item.duration) : 'AUDIO'}
                </div>
            ` : ''}
            ${isFavorite ? `
                <div class="favorite-badge">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#ff4757">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </div>
            ` : ''}
            <div class="photo-overlay">
                <div class="photo-info">${item.original_filename}</div>
            </div>
        </div>
    `;
}

// Modal Functions
function openModal(index) {
    currentIndex = index;
    const item = currentMedia[currentIndex];

    elements.mediaModal.classList.add('active');
    document.body.style.overflow = 'hidden';

    displayMedia(item);
    displayMetadata(item);
}

function closeModal() {
    // Pausar video si hay uno reproduciéndose
    const video = elements.mediaDisplay.querySelector('video');
    const audio = elements.mediaDisplay.querySelector('audio');
    if (video) {
        video.pause();
        video.currentTime = 0; // Opcional: reiniciar al inicio
    }

    if (audio) {
        audio.pause();
        audio.currentTime = 0;
    }

    elements.mediaModal.classList.remove('active');
    document.body.style.overflow = '';
}

function showPreviousMedia() {
    if (currentIndex > 0) {
        currentIndex--;
        const item = currentMedia[currentIndex];
        displayMedia(item);
        displayMetadata(item);
    }
}

function showNextMedia() {
    if (currentIndex < currentMedia.length - 1) {
        currentIndex++;
        const item = currentMedia[currentIndex];
        displayMedia(item);
        displayMetadata(item);
    }
}

function displayMedia(item) {
    const isVideo = item.file_type === 'video';
    const isAudio = item.file_type === 'audio';

    if (isVideo) {
        const videoId = `video-${Date.now()}`;
        elements.mediaDisplay.innerHTML = `
        <div class="custom-video-player" id="customPlayer-${videoId}">
            <video 
                id="${videoId}"
                src="${item.file_url}" 
                ${item.file_type === 'gif' ? 'loop muted autoplay' : ''}
                controlsList="nodownload"
                disablePictureInPicture
                oncontextmenu="return false;"
            >
                Tu navegador no soporta video.
            </video>
            ${item.file_type !== 'gif' ? `
                <div class="custom-video-controls">
                    <div class="video-progress-container" onclick="seekVideo('${videoId}', event)">
                        <div class="video-progress-bar" id="progress-${videoId}"></div>
                    </div>
                    <div class="video-controls-row">
                        <button class="video-control-btn" onclick="togglePlayPause('${videoId}')">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="white" id="playIcon-${videoId}">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </button>
                        <span class="video-time" id="time-${videoId}">0:00 / 0:00</span>
                        <div class="video-volume-container">
                            <button class="video-control-btn" onclick="toggleMute('${videoId}')">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" id="volumeIcon-${videoId}">
                                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                                </svg>
                            </button>
                            <input 
                                type="range" 
                                class="video-volume-slider" 
                                min="0" 
                                max="100" 
                                value="100"
                                oninput="changeVolume('${videoId}', this.value)"
                            >
                        </div>
                    </div>
                </div>
            ` : ''}
        </div>
    `;

        // Inicializar controles del video
        if (item.file_type !== 'gif') {
            initVideoPlayer(videoId);
        }
    } else if (isAudio) {
        elements.mediaDisplay.innerHTML = `
            <div class="audio-player">
                <div class="audio-artwork">
                    <svg width="200" height="200" viewBox="0 0 200 200" class="audio-disc">
                        <defs>
                            <linearGradient id="discGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                            </linearGradient>
                            <radialGradient id="discShine">
                                <stop offset="0%" style="stop-color:#ffffff;stop-opacity:0.3" />
                                <stop offset="100%" style="stop-color:#000000;stop-opacity:0.1" />
                            </radialGradient>
                        </defs>
                        <!-- Disco exterior -->
                        <circle cx="100" cy="100" r="95" fill="url(#discGradient)" stroke="#4a5568" stroke-width="2"/>
                        <!-- Brillo -->
                        <circle cx="100" cy="100" r="95" fill="url(#discShine)" opacity="0.6"/>
                        <!-- Centro del disco -->
                        <circle cx="100" cy="100" r="25" fill="#2d3748"/>
                        <circle cx="100" cy="100" r="20" fill="#1a202c"/>
                        <!-- Nota musical -->
                        <g transform="translate(100, 100)">
                            <circle cx="-25" cy="20" r="15" fill="white"/>
                            <rect x="-18" y="-30" width="8" height="50" fill="white" rx="4"/>
                            <ellipse cx="5" cy="-30" rx="18" ry="12" fill="white"/>
                        </g>
                    </svg>
                </div>
                <div class="audio-info">
                    <h3 class="audio-title">${item.custom_name || item.original_filename}</h3>
                    ${item.duration ? `<p class="audio-duration">⏱ ${formatDuration(item.duration)}</p>` : ''}
                </div>
                <audio src="${item.file_url}" controls autoplay class="audio-controls">
                    Tu navegador no soporta el elemento de audio.
                </audio>
            </div>
        `;
    } else {
        elements.mediaDisplay.innerHTML = `
            <img src="${item.file_url}" alt="${item.original_filename}">
        `;
    }
}

function displayMetadata(item) {
    const isFavorite = item.is_favorite === '1' || item.is_favorite === 1;
    const metadata = [];

    // Action buttons
    const actionButtons = `
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <button onclick="toggleFavorite(${item.id}, ${!isFavorite})" style="flex: 1; min-width: 120px; padding: 12px; background: ${isFavorite ? '#ff4757' : '#667eea'}; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                ${isFavorite ? 'Quitar favorito' : 'Favorito'}
            </button>
            ${currentAlbum ? `
                <button onclick="removeFromAlbum(${currentAlbum.id}, ${item.id})" style="flex: 1; min-width: 120px; padding: 12px; background: #ff6b6b; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        <line x1="9" y1="14" x2="15" y2="14"/>
                    </svg>
                    Quitar del álbum
                </button>
            ` : `
                <button onclick="showAddToAlbumModal(${item.id})" style="flex: 1; min-width: 120px; padding: 12px; background: #48dbfb; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        <line x1="12" y1="11" x2="12" y2="17"/>
                        <line x1="9" y1="14" x2="15" y2="14"/>
                    </svg>
                    Añadir a álbum
                </button>
            `}
            <button onclick="confirmDelete(${item.id})" style="padding: 12px 20px; background: #ee5a6f; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle;">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </button>
        </div>
    `;

    // Basic info
    metadata.push(`
        <div class="metadata-group">
            <div class="metadata-label">Nombre del archivo</div>
            <div class="metadata-value">${item.original_filename}</div>
        </div>
    `);

    // Date taken
    if (item.date_taken) {
        metadata.push(`
            <div class="metadata-group">
                <div class="metadata-label">Fecha de ${item.file_type === 'video' ? 'grabación' : 'captura'}</div>
                <div class="metadata-value">${formatDateTime(item.date_taken)}</div>
            </div>
        `);
    }

    // Duration (for videos/gifs)
    if (item.duration) {
        metadata.push(`
            <div class="metadata-group">
                <div class="metadata-label">Duración</div>
                <div class="metadata-value">${formatDuration(item.duration)}</div>
            </div>
        `);
    }

    // Dimensions
    if (item.width && item.height) {
        const megapixels = ((item.width * item.height) / 1000000).toFixed(1);
        metadata.push(`
            <div class="metadata-group">
                <div class="metadata-label">Dimensiones</div>
                <div class="metadata-value">${item.width} × ${item.height} px (${megapixels} MP)</div>
            </div>
        `);
    }

    // File size
    if (item.file_size) {
        metadata.push(`
            <div class="metadata-group">
                <div class="metadata-label">Tamaño</div>
                <div class="metadata-value">${formatFileSize(item.file_size)}</div>
            </div>
        `);
    }

    // Technical info (FPS, codec, bitrate)
    if (item.location_name && !item.gps_latitude) {
        metadata.push(`
            <div class="metadata-group">
                <div class="metadata-label">Información técnica</div>
                <div class="metadata-value">${item.location_name}</div>
            </div>
        `);
    }

    // Camera info
    if (item.camera_make || item.camera_model) {
        metadata.push(`
            <div class="metadata-group">
                <div class="metadata-label">Cámara</div>
                <div class="metadata-value">${item.camera_make || ''} ${item.camera_model || ''}</div>
            </div>
        `);
    }

    // Photo settings
    if (item.focal_length || item.aperture || item.iso || item.exposure_time) {
        let settings = '<div class="metadata-group"><div class="metadata-label">Configuración</div>';

        const settingsArray = [];
        if (item.focal_length) settingsArray.push(item.focal_length);
        if (item.aperture) settingsArray.push(item.aperture);
        if (item.iso) settingsArray.push(item.iso);
        if (item.exposure_time) settingsArray.push(item.exposure_time);

        settings += `<div class="metadata-value">${settingsArray.join(' • ')}</div>`;
        settings += '</div>';
        metadata.push(settings);
    }

    // GPS
    if (item.gps_latitude && item.gps_longitude) {
        const mapsUrl = `https://www.google.com/maps?q=${item.gps_latitude},${item.gps_longitude}`;
        metadata.push(`
            <div class="metadata-group">
                <div class="metadata-label">Ubicación</div>
                <div class="metadata-value">
                    ${item.location_name || 'Ubicación GPS disponible'}<br>
                    <small style="opacity: 0.7;">${Number(item.gps_latitude).toFixed(6)}, ${Number(item.gps_longitude).toFixed(6)}</small><br>
                    <a href="${mapsUrl}" target="_blank" style="color: var(--primary-color); text-decoration: none; font-size: 13px;">
                        📍 Ver en Google Maps
                    </a>
                </div>
            </div>
        `);
    }

    elements.mediaInfo.innerHTML = `
        <h3>Información</h3>
        ${actionButtons}
        ${metadata.join('')}
    `;
}

// Toggle Favorite
async function toggleFavorite(id, isFavorite) {
    try {
        const result = await apiRequest(`media/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ is_favorite: isFavorite })
        });

        if (result.success) {
            showNotification(isFavorite ? 'Agregado a favoritos' : 'Quitado de favoritos', 'success');

            // Update current media
            const mediaIndex = currentMedia.findIndex(m => m.id == id);
            if (mediaIndex !== -1) {
                currentMedia[mediaIndex].is_favorite = isFavorite ? 1 : 0;
            }

            // Refresh display
            loadMedia();
            if (elements.mediaModal.classList.contains('active')) {
                displayMetadata(currentMedia[currentIndex]);
            }
        }
    } catch (error) {
        showNotification('Error al actualizar favorito', 'error');
    }
}

// Confirm Delete
function confirmDelete(id) {
    if (confirm('¿Estás seguro de que quieres eliminar este archivo? Esta acción no se puede deshacer.')) {
        deleteMedia(id);
    }
}

// Delete Media
async function deleteMedia(id) {
    try {
        const result = await apiRequest(`media/${id}`, {
            method: 'DELETE'
        });

        if (result.success) {
            showNotification('Archivo eliminado exitosamente', 'success');
            closeModal();
            loadMedia();
            loadStats();
        }
    } catch (error) {
        showNotification('Error al eliminar archivo', 'error');
    }
}

// ===== ÁLBUMES =====

// Load Albums
async function loadAlbums() {
    showLoading();

    try {
        const result = await apiRequest('albums');

        if (result.success && result.data) {
            displayAlbums(result.data);
        }
    } catch (error) {
        console.error('Error loading albums:', error);
        showEmptyState();
    }
}

// Display Albums
function displayAlbums(albums) {
    hideLoading();
    elements.emptyState.style.display = 'none';
    elements.photosContainer.style.display = 'block';

    const html = `
        <div class="timeline-section">
            <div class="timeline-header">
                <span class="timeline-date">Álbumes</span>
                <span class="timeline-count">${albums.length} ${albums.length === 1 ? 'álbum' : 'álbumes'}</span>
                <button onclick="showCreateAlbumModal()" style="padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; margin-left: auto;">
                    + Crear álbum
                </button>
            </div>
            ${albums.length === 0 ? `
                <div class="empty-state">
                    <h2>No hay álbumes todavía</h2>
                    <p>Crea tu primer álbum para organizar tus fotos</p>
                    <button onclick="showCreateAlbumModal()" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; margin-top: 20px;">
                        Crear primer álbum
                    </button>
                </div>
            ` : `
                <div class="photos-grid">
                    ${albums.map(album => {
        const isPrivate = album.type === 'private';
        // Escapar comillas para cadenas JS
        const safeName = album.name.replace(/'/g, "\\'");
        // Construir llamada a función adecuada
        const clickAction = isPrivate ? `showPasswordModal(${album.id}, '${safeName}')` : `openAlbum(${album.id}, '${safeName}')`;
        const coverContent = isPrivate
            ? `<div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 40px;">🔒</div>`
            : (album.cover_url ? `<img src="${album.cover_url}" alt="${album.name}" loading="lazy">` :
                `<div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">📁</div>`);

        return `
                        <div class="photo-item album-item" onclick="${clickAction}">
                            ${coverContent}
                            ${isPrivate ? '<div class="private-badge" style="position:absolute; top:10px; right:12px; background:rgba(0,0,0,0.6); color:white; padding:4px 8px; border-radius:12px; font-size:12px; z-index:2;">Privado</div>' : ''}
                            <div class="photo-overlay">
                                <div class="photo-info">
                                    <strong>${album.name}</strong><br>
                                    ${album.media_count || 0} elementos
                                </div>
                            </div>
                           <button onclick="event.stopPropagation(); showEditAlbumModal(${album.id}, '${safeName}', '${(album.description || '').replace(/'/g, "\\'")}');" style="position: absolute; top: 10px; right: 50px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                ✏️
                            </button>
                            <button onclick="event.stopPropagation(); confirmDeleteAlbum(${album.id}, '${safeName}')" style="position: absolute; top: 10px; right: 10px; background: rgba(238, 90, 111, 0.9); color: white; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                🗑️
                            </button>
                        </div>
                    `}).join('')}
                </div>
            `}
        </div>
    `;

    elements.photosContainer.innerHTML = html;
}

// Private Album Logic
let pendingPrivateAlbum = null;

function showPasswordModal(albumId, albumName) {
    pendingPrivateAlbum = { id: albumId, name: albumName };

    // Crear modal si no existe
    let modal = document.getElementById('passwordModal');
    if (!modal) {
        createPasswordModalHTML();
        modal = document.getElementById('passwordModal');
        // Add Listeners
        document.getElementById('passwordModalClose').addEventListener('click', closePasswordModal);
        document.getElementById('unlockAlbumBtn').addEventListener('click', unlockPrivateAlbum);
        document.getElementById('albumPasswordInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') unlockPrivateAlbum();
        });
    } else {
        document.getElementById('albumPasswordInput').value = '';
    }

    modal.classList.add('active');
    setTimeout(() => document.getElementById('albumPasswordInput').focus(), 100);
}

function closePasswordModal() {
    const modal = document.getElementById('passwordModal');
    if (modal) modal.classList.remove('active');
    pendingPrivateAlbum = null;
}

function createPasswordModalHTML() {
    const div = document.createElement('div');
    div.id = 'passwordModal';
    div.className = 'password-modal'; // Use class instead of inline styles

    div.innerHTML = `
        <div class="password-modal-content">
            <div class="password-modal-header">
                <h3><span style="font-size: 24px;">🔒</span> Álbum Privado</h3>
                <button class="modal-close" id="passwordModalClose" style="position: static; color: var(--text-secondary);">&times;</button>
            </div>
            <div class="modal-body" style="display: block;">
                <p>Este álbum está protegido. Ingresa la contraseña para acceder.</p>
                <div class="password-form-group">
                    <input type="password" id="albumPasswordInput" class="password-input" placeholder="••••••">
                </div>
                <button id="unlockAlbumBtn" class="unlock-btn">Desbloquear</button>
            </div>
        </div>
    `;
    document.body.appendChild(div);
}

// Estilo para clase active
const passwordModalStyle = document.createElement('style');
passwordModalStyle.innerHTML = `
/* Styles moved to external sheet */
`;
document.head.appendChild(passwordModalStyle);

async function unlockPrivateAlbum() {
    if (!pendingPrivateAlbum) return;

    const passwordInput = document.getElementById('albumPasswordInput');
    const password = passwordInput.value;

    if (!password) {
        showNotification('Ingresa la contraseña', 'error');
        return;
    }

    const btn = document.getElementById('unlockAlbumBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Verificando...';
    btn.disabled = true;

    try {
        await openAlbum(pendingPrivateAlbum.id, pendingPrivateAlbum.name, password);
    } catch (e) {
        // Error handling inside openAlbum
    } finally {
        if (btn) {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }
}

// Open Album
async function openAlbum(albumId, albumName, password = null) {
    currentAlbum = { id: albumId, name: albumName };
    showLoading();

    // Si hay password, cerrar el modal primero
    if (password) {
        closePasswordModal();
    }

    try {
        // Construir URL con password si existe
        let url = `albums/${albumId}/media`;
        if (password) {
            url += `?password=${encodeURIComponent(password)}`;
        }

        const result = await apiRequest(url);

        if (result.success && result.data) {
            currentMedia = result.data; // Aquí result.data ya contiene las fotos

            hideLoading();
            elements.emptyState.style.display = 'none';
            elements.photosContainer.style.display = 'block';

            // Construir HTML del álbum
            const html = `
                <div class="timeline-section">
                    <div style="display: flex; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                         <button onclick="loadAlbums()" style="background: none; border: none; font-size: 24px; cursor: pointer; margin-right: 10px;">←</button>
                         <div>
                            <h2 style="margin: 0;">${albumName} ${password ? '🔓' : ''}</h2>
                            <span style="color: #666; font-size: 14px;">${currentMedia.length} elementos</span>
                         </div>
                         <div style="margin-left: auto; display: flex; gap: 8px;" id="shareButtons-${albumId}">
                            <button onclick="shareAlbum(${albumId})" style="padding: 8px 16px; background: linear-gradient(135deg, #10ac84, #0e9c75); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 6px; font-weight: 500;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="18" cy="5" r="3"></circle>
                                    <circle cx="6" cy="12" r="3"></circle>
                                    <circle cx="18" cy="19" r="3"></circle>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                </svg>
                                Compartir Álbum
                            </button>
                        </div>
                    </div>
                    
                    ${currentMedia.length === 0 ? `
                        <div class="empty-state">
                            <h2>Este álbum está vacío</h2>
                            <p>Abre una foto y usa "Añadir a álbum" para agregarla aquí</p>
                        </div>
                    ` : `
                        <div class="photos-grid">
                            ${currentMedia.map((item, index) => createPhotoItem(item, index)).join('')}
                        </div>
                    `}
                </div>
            `;

            elements.photosContainer.innerHTML = html;

            // Add click listeners
            document.querySelectorAll('.photo-item').forEach(item => {
                const index = parseInt(item.dataset.index);
                if (!isNaN(index)) { // Asegurar que es un índice válido
                    item.addEventListener('click', () => {
                        openModal(index);
                    });
                }
            });
        }
    } catch (error) {
        console.error('Error loading album:', error);

        // Manejar error de password incorrecto (403 normalmente lanza error en apiRequest o retorna json con error)
        // Como apiRequest lanza error si !response.ok, necesitamos capturar el 403.
        // Mi implementación de apiRequest lanza error con status.

        if (error.message.includes('403') || error.message.includes('password')) {
            showNotification('Contraseña incorrecta', 'error');
            showPasswordModal(albumId, albumName);
        } else {
            showNotification('Error al cargar el álbum', 'error');
        }
        hideLoading();
    }
}

// Show Create Album Modal
function showCreateAlbumModal() {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;

    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%;">
            <h2 style="margin-top: 0;">Crear Nuevo Álbum</h2>
            <input type="text" id="albumName" placeholder="Nombre del álbum" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 15px; font-size: 16px;">
            <textarea id="albumDescription" placeholder="Descripción (opcional)" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; font-size: 16px; min-height: 80px; resize: vertical;"></textarea>
            <div style="display: flex; gap: 10px;">
                <button onclick="this.closest('div').parentElement.parentElement.remove()" style="flex: 1; padding: 12px; background: #ddd; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;">
                    Cancelar
                </button>
                <button onclick="createAlbum()" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;">
                    Crear
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    document.getElementById('albumName').focus();
}

// Create Album
async function createAlbum() {
    const name = document.getElementById('albumName').value.trim();
    const description = document.getElementById('albumDescription').value.trim();

    if (!name) {
        showNotification('El nombre del álbum es obligatorio', 'error');
        return;
    }

    try {
        const result = await apiRequest('albums', {
            method: 'POST',
            body: JSON.stringify({ name, description })
        });

        if (result.success) {
            showNotification('Álbum creado exitosamente', 'success');
            document.querySelector('[style*="z-index: 10000"]').remove();
            loadAlbums();
        }
    } catch (error) {
        showNotification('Error al crear el álbum', 'error');
    }
}


// ============================================
// SELECTION & BULK ACTIONS
// ============================================

function handleItemClick(element, event) {
    // Si se hizo click en el checkbox o estamos en modo selección
    if (event.target.classList.contains('select-checkbox') || isSelectionMode) {
        event.stopPropagation();
        event.preventDefault();

        const index = parseInt(element.dataset.index);
        const item = currentMedia[index];

        if (selectedItems.has(item.id)) {
            selectedItems.delete(item.id);
            element.classList.remove('selected');
        } else {
            selectedItems.add(item.id);
            element.classList.add('selected');
        }

        updateSelectionUI();
    } else {
        // Comportamiento normal (abrir modal)
        const index = parseInt(element.dataset.index);
        openModal(index);
    }
}

function toggleSelectionMode() {
    isSelectionMode = !isSelectionMode;

    // Toggle UI classes
    document.querySelectorAll('.photo-item').forEach(item => {
        if (isSelectionMode) {
            item.classList.add('selection-mode');
            item.querySelector('.select-checkbox').style.display = 'flex';
        } else {
            item.classList.remove('selection-mode');
            item.querySelector('.select-checkbox').style.display = 'none';
        }
    });

    // Show/Hide Action Bar
    if (isSelectionMode) {
        elements.bulkActionBar.classList.add('active');
        elements.selectModeBtn.classList.add('active'); // Optional styling
    } else {
        elements.bulkActionBar.classList.remove('active');
        elements.selectModeBtn.classList.remove('active');
        // Clear selection when exiting? Maybe no, user might want to cancel and resume
        selectedItems.clear();
        document.querySelectorAll('.photo-item.selected').forEach(item => item.classList.remove('selected'));
        updateSelectionUI();
    }
}

function updateSelectionUI() {
    elements.selectedCount.textContent = `${selectedItems.size} seleccionados`;

    if (selectedItems.size > 0) {
        elements.deleteSelectedBtn.removeAttribute('disabled');
        elements.deleteSelectedBtn.style.opacity = '1';
    } else {
        elements.deleteSelectedBtn.setAttribute('disabled', 'true');
        elements.deleteSelectedBtn.style.opacity = '0.5';
    }
}

// Event Listeners for Selection
document.addEventListener('DOMContentLoaded', () => {
    if (elements.selectModeBtn) {
        elements.selectModeBtn.addEventListener('click', toggleSelectionMode);
    }

    if (elements.closeSelectionBtn) {
        elements.closeSelectionBtn.addEventListener('click', toggleSelectionMode);
    }

    if (elements.deleteSelectedBtn) {
        elements.deleteSelectedBtn.addEventListener('click', confirmBulkDelete);
    }
});

function confirmBulkDelete() {
    if (selectedItems.size === 0) return;

    if (confirm(`¿Estás seguro de que deseas eliminar ${selectedItems.size} elementos? Esta acción no se puede deshacer.`)) {
        performBulkDelete();
    }
}

async function performBulkDelete() {
    const idsToDelete = Array.from(selectedItems);

    // Show loading state on button
    const originalText = elements.deleteSelectedBtn.innerHTML;
    elements.deleteSelectedBtn.innerHTML = 'Eliminando...';
    elements.deleteSelectedBtn.disabled = true;

    try {
        const result = await apiRequest('media/bulk-delete', {
            method: 'POST',
            body: JSON.stringify({ ids: idsToDelete })
        });

        if (result.success) {
            showNotification(`${result.stats.deleted} elementos eliminados exitosamente`, 'success');

            if (result.stats.failed > 0) {
                showNotification(`Hubo error al eliminar ${result.stats.failed} elementos`, 'warning');
            }

            // Exit selection mode
            toggleSelectionMode();

            // Refresh grid
            loadMedia();
            loadStats();
        }
    } catch (error) {
        console.error('Error delete bulk:', error);
        showNotification('Error al eliminar los elementos seleccionados', 'error');
    }
}

// Add to Album Bulk Logic
function initBulkAddListeners() {
    if (elements.bulkAddToAlbumBtn) {
        elements.bulkAddToAlbumBtn.addEventListener('click', openAddToAlbumModal);
    }

    if (elements.cancelAddToAlbumBtn) {
        elements.cancelAddToAlbumBtn.addEventListener('click', closeAddToAlbumModal);
    }

    if (elements.addToAlbumModalOverlay) {
        elements.addToAlbumModalOverlay.addEventListener('click', closeAddToAlbumModal);
    }

    if (elements.confirmAddToAlbumBtn) {
        elements.confirmAddToAlbumBtn.addEventListener('click', performBulkAddToAlbum);
    }
}

// Call this in DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    initBulkAddListeners();
});

function openAddToAlbumModal() {
    if (selectedItems.size === 0) return;

    elements.addToAlbumModal.classList.add('active');
    loadAlbumsForSelect();
}

function closeAddToAlbumModal() {
    elements.addToAlbumModal.classList.remove('active');
}

async function loadAlbumsForSelect() {
    elements.albumSelect.innerHTML = '<option value="">Cargando...</option>';
    elements.confirmAddToAlbumBtn.disabled = true;

    try {
        const result = await apiRequest('albums');
        if (result.success && result.data.length > 0) {
            elements.albumSelect.innerHTML = '<option value="">Selecciona un álbum</option>';
            result.data.forEach(album => {
                const option = document.createElement('option');
                option.value = album.id;
                option.textContent = album.name;
                elements.albumSelect.appendChild(option);
            });
            elements.confirmAddToAlbumBtn.disabled = false;
        } else {
            elements.albumSelect.innerHTML = '<option value="">No hay álbumes creados</option>';
        }
    } catch (error) {
        console.error('Error loading albums:', error);
        elements.albumSelect.innerHTML = '<option value="">Error al cargar álbumes</option>';
    }
}

async function performBulkAddToAlbum() {
    const albumId = elements.albumSelect.value;
    if (!albumId) {
        showNotification('Por favor selecciona un álbum', 'warning');
        return;
    }

    const idsToAdd = Array.from(selectedItems);

    // UI Loading state
    const originalText = elements.confirmAddToAlbumBtn.innerHTML;
    elements.confirmAddToAlbumBtn.innerHTML = 'Añadiendo...';
    elements.confirmAddToAlbumBtn.disabled = true;

    try {
        const result = await apiRequest(`albums/${albumId}/add-bulk`, {
            method: 'POST',
            body: JSON.stringify({ media_ids: idsToAdd })
        });

        if (result.success) {
            showNotification(result.message || 'Elementos añadidos al álbum', 'success');
            closeAddToAlbumModal();
            toggleSelectionMode(); // Exit selection mode
        }
    } catch (error) {
        console.error('Error bulk add to album:', error);
        showNotification('Error al añadir elementos al álbum', 'error');
    } finally {
        elements.confirmAddToAlbumBtn.innerHTML = originalText;
        elements.confirmAddToAlbumBtn.disabled = false;
    }
}
function showEditAlbumModal(albumId, currentName, currentDescription) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;

    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%;">
            <h2 style="margin-top: 0;">Editar Álbum</h2>
            <input type="text" id="editAlbumName" value="${currentName}" placeholder="Nombre del álbum" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 15px; font-size: 16px;">
            <textarea id="editAlbumDescription" placeholder="Descripción (opcional)" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; font-size: 16px; min-height: 80px; resize: vertical;">${currentDescription}</textarea>
            <div style="display: flex; gap: 10px;">
                <button onclick="this.closest('div').parentElement.parentElement.remove()" style="flex: 1; padding: 12px; background: #ddd; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;">
                    Cancelar
                </button>
                <button onclick="updateAlbum(${albumId})" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;">
                    Guardar
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    document.getElementById('editAlbumName').focus();
}

// Update Album
async function updateAlbum(albumId) {
    const name = document.getElementById('editAlbumName').value.trim();
    const description = document.getElementById('editAlbumDescription').value.trim();

    if (!name) {
        showNotification('El nombre del álbum es obligatorio', 'error');
        return;
    }

    try {
        const result = await apiRequest(`albums/${albumId}`, {
            method: 'PUT',
            body: JSON.stringify({ name, description })
        });

        if (result.success) {
            showNotification('Álbum actualizado exitosamente', 'success');
            document.querySelector('[style*="z-index: 10000"]').remove();
            loadAlbums();
        }
    } catch (error) {
        showNotification('Error al actualizar el álbum', 'error');
    }
}

// Confirm Delete Album
function confirmDeleteAlbum(albumId, albumName) {
    if (confirm(`¿Estás seguro de que quieres eliminar el álbum "${albumName}"? Las fotos no se eliminarán, solo el álbum.`)) {
        deleteAlbum(albumId);
    }
}

// Delete Album
async function deleteAlbum(albumId) {
    try {
        const result = await apiRequest(`albums/${albumId}`, {
            method: 'DELETE'
        });

        if (result.success) {
            showNotification('Álbum eliminado exitosamente', 'success');
            loadAlbums();
        }
    } catch (error) {
        showNotification('Error al eliminar el álbum', 'error');
    }
}

// Show Add to Album Modal
async function showAddToAlbumModal(mediaId) {
    try {
        const result = await apiRequest('albums');

        if (!result.success || !result.data) {
            showNotification('Error al cargar álbumes', 'error');
            return;
        }

        const albums = result.data;

        if (albums.length === 0) {
            if (confirm('No tienes álbumes. ¿Quieres crear uno ahora?')) {
                showCreateAlbumModal();
            }
            return;
        }

        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
        `;

        modal.innerHTML = `
            <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                <h2 style="margin-top: 0;">Añadir a Álbum</h2>
                <div style="margin-bottom: 20px;">
                    ${albums.map(album => `
                        <div onclick="addMediaToAlbum(${mediaId}, ${album.id})" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='white'">
                            <strong>${album.name}</strong><br>
                            <small style="color: #666;">${album.media_count || 0} elementos</small>
                        </div>
                    `).join('')}
                </div>
                <button onclick="this.closest('div').parentElement.remove()" style="width: 100%; padding: 12px; background: #ddd; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;">
                    Cancelar
                </button>
            </div>
        `;

        document.body.appendChild(modal);
    } catch (error) {
        showNotification('Error al cargar álbumes', 'error');
    }
}

// Add Media to Album
async function addMediaToAlbum(mediaId, albumId) {
    try {
        const result = await apiRequest(`albums/${albumId}/media`, {
            method: 'POST',
            body: JSON.stringify({ media_id: mediaId })
        });

        if (result.success) {
            showNotification('Añadido al álbum exitosamente', 'success');
            document.querySelector('[style*="z-index: 10001"]').remove();
        }
    } catch (error) {
        showNotification('Error al añadir al álbum', 'error');
    }
}

// Remove Media from Album
async function removeFromAlbum(albumId, mediaId) {
    if (!confirm('¿Quitar este elemento del álbum?')) {
        return;
    }

    try {
        const result = await apiRequest(`albums/${albumId}/media/${mediaId}`, {
            method: 'DELETE'
        });

        if (result.success) {
            showNotification('Quitado del álbum', 'success');
            closeModal();
            // Recargar el álbum
            openAlbum(albumId, currentAlbum.name);
        }
    } catch (error) {
        showNotification('Error al quitar del álbum', 'error');
    }
}

// ===== UPLOAD =====

async function uploadFiles(files) {
    for (let i = 0; i < files.length; i++) {
        await uploadFile(files[i], i + 1, files.length);
    }

    setTimeout(() => {
        loadMedia();
        loadStats();
    }, 500);
}

async function uploadFile(file, current, total) {
    const formData = new FormData();
    formData.append('file', file);

    elements.uploadProgress.style.display = 'block';
    elements.uploadFileName.textContent = `Subiendo ${file.name} (${current}/${total})`;

    try {
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                elements.uploadPercent.textContent = `${percent}%`;
                elements.progressFill.style.width = `${percent}%`;
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status === 200 || xhr.status === 201) {
                showNotification(`${file.name} subido exitosamente`, 'success');
            } else {
                showNotification(`Error al subir ${file.name}`, 'error');
            }
        });

        xhr.addEventListener('error', () => {
            showNotification(`Error al subir ${file.name}`, 'error');
        });

        xhr.open('POST', API_BASE + 'media');
        xhr.send(formData);

        await new Promise(resolve => {
            xhr.addEventListener('loadend', resolve);
        });

    } catch (error) {
        console.error('Upload error:', error);
        showNotification(`Error al subir ${file.name}`, 'error');
    }

    if (current === total) {
        setTimeout(() => {
            elements.uploadProgress.style.display = 'none';
        }, 1000);
    }
}

// Search Media
async function searchMedia(query) {
    if (!query.trim()) {
        loadMedia();
        return;
    }

    showLoading();

    try {
        const result = await apiRequest(`search?q=${encodeURIComponent(query)}`);

        if (result.success && result.data) {
            currentMedia = result.data;

            if (currentMedia.length === 0) {
                showEmptyState();
            } else {
                displayTimeline(currentMedia);
            }
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

// Load Stats
async function loadStats() {
    try {
        const result = await apiRequest('stats');

        if (result.success && result.data) {
            const stats = result.data;

            const totalSize = stats.total_size || 0;
            const maxSize = 30 * 1024 * 1024 * 1024; // 30GB
            const percentage = (totalSize / maxSize) * 100;

            elements.storageBar.style.width = `${Math.min(percentage, 100)}%`;
            elements.storageText.textContent = `${formatFileSize(totalSize)} de ${formatFileSize(maxSize)} usados`;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Utility Functions
function showLoading() {
    elements.loadingState.style.display = 'flex';
    elements.emptyState.style.display = 'none';
    elements.photosContainer.style.display = 'none';
}

function hideLoading() {
    elements.loadingState.style.display = 'none';
}

function showEmptyState() {
    hideLoading();
    elements.emptyState.style.display = 'flex';
    elements.photosContainer.style.display = 'none';
}

function formatDate(dateString) {
    if (dateString === 'Sin fecha') return dateString;

    // Fix timezone bug: parse date as local time, not UTC
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    date.setHours(0, 0, 0, 0);

    if (date.getTime() === today.getTime()) {
        return 'Hoy';
    } else if (date.getTime() === yesterday.getTime()) {
        return 'Ayer';
    }

    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('es-ES', options);
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('es-ES', options);
}

function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    } else {
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

function showNotification(message, type = 'info') {
    console.log(`[${type.toUpperCase()}] ${message}`);

    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    toast.textContent = message;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
`;

// ============================================
// COMPARTIR ÁLBUMES
// ============================================

async function shareAlbum(albumId) {
    try {
        // Primero verificar si ya está compartido
        const infoResponse = await fetch(`${API_BASE}/share.php?action=info&album_id=${albumId}`);
        const infoResult = await infoResponse.json();

        if (infoResult.shared) {
            // Ya está compartido, mostrar info con configuración actual
            showShareModal(infoResult, currentAlbum.name, albumId, true);
            return;
        }

        // No está compartido, crear nuevo enlace
        const response = await fetch(`${API_BASE}/share.php?action=create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ album_id: albumId })
        });

        const result = await response.json();

        if (result.success) {
            // Construir objeto info inicial
            const initialInfo = {
                share_url: result.share_url,
                allow_upload: false,
                access_emails: []
            };
            showShareModal(initialInfo, result.album_name, albumId, false);
        } else {
            showNotification(result.message || 'Error al compartir', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al crear enlace de compartir', 'error');
    }
}

function showShareModal(shareInfo, albumName, albumId, alreadyShared) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;

    const allowUpload = shareInfo.allow_upload || false;
    const emails = shareInfo.access_emails || [];
    const emailsString = emails.join(', ');

    modal.innerHTML = `
        <div style="background: white; border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3 style="font-size: 24px; margin-bottom: 16px; color: #202124;">🔗 ${alreadyShared ? 'Álbum Compartido' : 'Compartir'} "${albumName}"</h3>
            <p style="color: #5f6368; margin-bottom: 20px;">
                ${alreadyShared ? 'Este álbum ya está compartido. Usa este enlace:' : 'Comparte este enlace para que otros puedan ver este álbum:'}
                <br><small>Cualquiera con el enlace podrá ver el álbum.</small>
            </p>
            
            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                <input 
                    type="text" 
                    value="${shareInfo.share_url}" 
                    readonly 
                    id="shareUrlInput"
                    style="flex: 1; padding: 12px; border: 2px solid #dadce0; border-radius: 8px; font-size: 14px; background: #f8f9fa;"
                >
                <button 
                    onclick="copyShareUrl()" 
                    style="padding: 12px 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;"
                    title="Copiar enlace"
                >
                    Copiar
                </button>
            </div>

            <div style="border-top: 1px solid #eee; padding-top: 20px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; margin-bottom: 12px; color: #202124;">Configuración de Colaboración</h4>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="allowUploadCheck" ${allowUpload ? 'checked' : ''} style="width: 18px; height: 18px; margin-right: 10px;">
                        <span style="font-size: 14px; color: #3c4043; font-weight: 500;">Permitir subir archivos</span>
                    </label>
                    <p style="font-size: 12px; color: #70757a; margin: 4px 0 0 28px;">
                        Solo los usuarios autorizados podrán subir fotos y videos.
                    </p>
                </div>

                <div id="emailsContainer" style="margin-bottom: 16px; ${allowUpload ? '' : 'display: none;'}">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #3c4043; margin-bottom: 6px;">
                        Correos autorizados (separados por coma):
                    </label>
                    <textarea 
                        id="allowedEmailsInput" 
                        placeholder="usuario1@email.com, usuario2@email.com"
                        style="width: 100%; padding: 10px; border: 1px solid #dadce0; border-radius: 8px; font-size: 14px; min-height: 60px; resize: vertical;"
                    >${emailsString}</textarea>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                     <button 
                        id="saveConfigBtn"
                        onclick="saveShareConfig(${albumId})" 
                        style="padding: 8px 16px; background: #1a73e8; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;"
                    >
                        Guardar Configuración
                    </button>
                </div>
            </div>

            <div style="display: flex; gap: 8px;">
                <button 
                    onclick="this.closest('div').parentElement.remove()" 
                    style="flex: 1; padding: 12px; background: #f1f3f4; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; color: #202124;"
                >
                    Cerrar
                </button>
                <button 
                    onclick="deleteShareLink(${albumId}); this.closest('div').parentElement.remove();" 
                    style="flex: 1; padding: 12px; background: #ee5a6f; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;"
                >
                    Dejar de Compartir
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Toggle emails visibility based on checkbox
    const checkbox = document.getElementById('allowUploadCheck');
    const emailsContainer = document.getElementById('emailsContainer');

    checkbox.addEventListener('change', (e) => {
        emailsContainer.style.display = e.target.checked ? 'block' : 'none';
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

function copyShareUrl() {
    const input = document.getElementById('shareUrlInput');
    input.select();
    document.execCommand('copy');
    showNotification('¡Enlace copiado al portapapeles!', 'success');
}

async function saveShareConfig(albumId) {
    const allowUpload = document.getElementById('allowUploadCheck').checked;
    const emailsText = document.getElementById('allowedEmailsInput').value;

    // Parse emails
    const emails = emailsText.split(',')
        .map(e => e.trim())
        .filter(e => e.length > 0);

    const btn = document.getElementById('saveConfigBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Guardando...';
    btn.disabled = true;

    try {
        const response = await fetch(`${API_BASE}/share.php?action=update_config`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                album_id: albumId,
                allow_upload: allowUpload,
                emails: emails
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Configuración guardada correctamente', 'success');
        } else {
            showNotification(result.message || 'Error al guardar configuración', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

async function deleteShareLink(albumId) {
    if (!confirm('¿Dejar de compartir este álbum? El enlace dejará de funcionar.')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/share.php?action=delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ album_id: albumId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Enlace eliminado. El álbum ya no está compartido', 'success');
        } else {
            showNotification(result.message || 'Error al eliminar enlace', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al eliminar enlace de compartir', 'error');
    }
}


// ============================================
// REPRODUCTOR DE VIDEO CUSTOM
// ============================================

function initVideoPlayer(videoId) {
    const video = document.getElementById(videoId);
    const progressBar = document.getElementById(`progress-${videoId}`);
    const timeDisplay = document.getElementById(`time-${videoId}`);

    if (!video) return;

    // Actualizar progreso
    video.addEventListener('timeupdate', () => {
        const percent = (video.currentTime / video.duration) * 100;
        progressBar.style.width = percent + '%';

        const current = formatVideoTime(video.currentTime);
        const total = formatVideoTime(video.duration);
        timeDisplay.textContent = `${current} / ${total}`;
    });

    // Auto-play
    video.play().catch(() => {
        // Si falla el autoplay, no hacer nada
    });
}

function togglePlayPause(videoId) {
    const video = document.getElementById(videoId);
    const playIcon = document.getElementById(`playIcon-${videoId}`);

    if (video.paused) {
        video.play();
        playIcon.innerHTML = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
    } else {
        video.pause();
        playIcon.innerHTML = '<polygon points="5 3 19 12 5 21 5 3"/>';
    }
}

function seekVideo(videoId, event) {
    const video = document.getElementById(videoId);
    const progressContainer = event.currentTarget;
    const rect = progressContainer.getBoundingClientRect();
    const percent = (event.clientX - rect.left) / rect.width;
    video.currentTime = percent * video.duration;
}

function toggleMute(videoId) {
    const video = document.getElementById(videoId);
    const volumeIcon = document.getElementById(`volumeIcon-${videoId}`);

    video.muted = !video.muted;

    if (video.muted) {
        volumeIcon.innerHTML = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line>';
    } else {
        volumeIcon.innerHTML = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>';
    }
}

function changeVolume(videoId, value) {
    const video = document.getElementById(videoId);
    video.volume = value / 100;
    if (value == 0) {
        video.muted = true;
    } else {
        video.muted = false;
    }
}

function formatVideoTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// ============================================
// SCROLL INFINITO
// ============================================

// Detectar cuando el usuario llega al final de la página
window.addEventListener('scroll', () => {
    // Solo en vista de fotos, no en álbumes
    if (currentView !== 'photos' || currentAlbum) return;

    const scrollPosition = window.innerHeight + window.scrollY;
    const pageHeight = document.documentElement.scrollHeight;

    // Si está a 500px del final, cargar más
    if (scrollPosition >= pageHeight - 500) {
        if (!isLoading && hasMoreMedia) {
            loadMedia(true); // true = append mode
        }
    }
});

document.head.appendChild(style);
