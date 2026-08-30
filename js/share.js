// Configuración
const API_BASE = 'api';

// Estado
let currentMedia = [];
let currentIndex = 0;
let albumData = null;
let permissions = null;
let currentUser = null;
let currentPassword = null;

// Verificar sesión al inicio
async function checkAuth() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=check`);
        const result = await response.json();
        if (result.authenticated) {
            currentUser = result.user;
        } else {
        }
        updateHeaderUI();
    } catch (e) {
        console.error('Auth check error', e);
    }
}

// Elementos DOM
const elements = {
    loadingContainer: document.getElementById('loadingContainer'),
    errorContainer: document.getElementById('errorContainer'),
    albumContainer: document.getElementById('albumContainer'),
    albumName: document.getElementById('albumName'),
    albumDescription: document.getElementById('albumDescription'),
    mediaCount: document.getElementById('mediaCount'),
    viewCount: document.getElementById('viewCount'),
    mediaGrid: document.getElementById('mediaGrid'),
    mediaModal: document.getElementById('mediaModal'),
    modalOverlay: document.getElementById('modalOverlay'),
    modalClose: document.getElementById('modalClose'),
    mediaDisplay: document.getElementById('mediaDisplay'),
    navPrev: document.getElementById('navPrev'),
    navNext: document.getElementById('navNext'),
    warningOverlay: document.getElementById('warningOverlay'),
    errorTitle: document.getElementById('errorTitle'),
    errorMessage: document.getElementById('errorMessage')
};

// ============================================
// PROTECCIONES ANTI-DESCARGA
// ============================================

// Deshabilitar clic derecho
document.addEventListener('contextmenu', (e) => {
    e.preventDefault();
    showWarning('Clic derecho deshabilitado');
    return false;
});

// Deshabilitar arrastrar imágenes/videos
document.addEventListener('dragstart', (e) => {
    e.preventDefault();
    return false;
});

// Deshabilitar selección de texto e imágenes
document.body.style.userSelect = 'none';
document.body.style.webkitUserSelect = 'none';
document.body.style.mozUserSelect = 'none';
document.body.style.msUserSelect = 'none';

// Detectar tecla PrintScreen
document.addEventListener('keyup', (e) => {
    if (e.key === 'PrintScreen' || e.keyCode === 44) {
        blurContent();
        showWarning('Capturas de pantalla no permitidas');
    }
});

// Detectar combinaciones de teclas para captura
document.addEventListener('keydown', (e) => {
    // Windows: Win + Shift + S, Win + PrtScn
    // Mac: Cmd + Shift + 3/4/5
    if ((e.metaKey || e.ctrlKey) && e.shiftKey && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        blurContent();
        showWarning('Capturas de pantalla no permitidas');
        return false;
    }
});

// Detectar herramientas de desarrollador (DevTools)
let devtoolsOpen = false;
const detectDevTools = () => {
    const threshold = 160;
    const widthThreshold = window.outerWidth - window.innerWidth > threshold;
    const heightThreshold = window.outerHeight - window.innerHeight > threshold;

    if (widthThreshold || heightThreshold) {
        if (!devtoolsOpen) {
            devtoolsOpen = true;
            blurContent();
            showWarning('Herramientas de desarrollador detectadas');
        }
    } else {
        if (devtoolsOpen) {
            devtoolsOpen = false;
            unblurContent();
        }
    }
};

setInterval(detectDevTools, 1000);

// Detectar pérdida de foco (posible captura de pantalla)
let blurTimeout;
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        blurTimeout = setTimeout(() => {
            blurContent();
            showWarning('Contenido protegido');
        }, 100);
    } else {
        clearTimeout(blurTimeout);
        setTimeout(unblurContent, 2000);
    }
});

// Funciones de blur
function blurContent() {
    document.body.style.filter = 'blur(20px)';
    document.body.style.pointerEvents = 'none';
}

function unblurContent() {
    document.body.style.filter = 'none';
    document.body.style.pointerEvents = 'auto';
}

function showWarning(message) {
    elements.warningOverlay.querySelector('p').textContent = message;
    elements.warningOverlay.style.display = 'flex';
    setTimeout(() => {
        elements.warningOverlay.style.display = 'none';
    }, 3000);
}

// ============================================
// CARGA DE ÁLBUM
// ============================================

async function loadSharedAlbum(password = null) {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (!token) {
        showError('Token no válido', 'No se proporcionó un token de acceso');
        return;
    }

    try {
        let url = `${API_BASE}/share.php?action=get&token=${token}`;
        if (password) {
            currentPassword = password;
            url += `&password=${encodeURIComponent(password)}`;
        }

        const response = await fetch(url);
        const result = await response.json();

        if (result.locked) {
            // Álbum bloqueado, mostrar modal de contraseña

            if (password) {
                // Si se envió password y volvió locked, es incorrecto
                // Podríamos mostrar un mensaje más sutil o vibrar el input
                const input = document.getElementById('passwordInput');
                if (input) {
                    input.value = '';
                    input.classList.add('error'); // Asumiendo que añadimos estilo para error
                    setTimeout(() => input.classList.remove('error'), 500);
                }
                alert("Contraseña incorrecta");
            }

            albumData = result.album;
            showPasswordModal();
            return;
        }

        if (!result.success) {
            throw new Error(result.message);
        }

        // Si se desbloqueó correctamente, ocultar modal
        closePasswordModal();

        albumData = result.album;
        currentMedia = result.media;
        permissions = result.permissions || { can_upload: false, user_email: null };

        updateHeaderUI();
        displayAlbum();
    } catch (error) {
        console.error('Error:', error);
        showError('Error al cargar', error.message || 'No se pudo cargar el álbum compartido');
    }
}

// Password Modal for Share Page
function showPasswordModal() {
    let modal = document.getElementById('passwordModal');
    if (!modal) {
        createPasswordModal();
        modal = document.getElementById('passwordModal');
    }

    // Update text if needed
    const title = modal.querySelector('h3');
    // if (title) title.textContent = '🔒 Álbum Protegido';

    modal.classList.add('active');

    const input = document.getElementById('passwordInput');
    if (input) {
        input.value = '';
        setTimeout(() => input.focus(), 100);
    }
}

function createPasswordModal() {
    const div = document.createElement('div');
    div.id = 'passwordModal';
    div.className = 'password-modal'; // New CSS class
    div.innerHTML = `
        <div class="password-modal-content">
            <div class="password-modal-header" style="justify-content: center;">
                <h3><span style="font-size: 24px;">🔒</span> Álbum Protegido</h3>
            </div>
            <div class="modal-body" style="display: block;">
                <p>Este álbum es privado. Por favor ingresa la contraseña para verlo.</p>
                <div class="password-form-group">
                    <input type="password" id="passwordInput" class="password-input" placeholder="••••••" style="text-align: center;">
                </div>
                <button id="unlockBtn" class="unlock-btn">Desbloquear</button>
            </div>
        </div>
    `;
    document.body.appendChild(div);

    // Re-attach listeners just in case
    const btn = document.getElementById('unlockBtn');
    const input = document.getElementById('passwordInput');

    if (btn) btn.addEventListener('click', submitPassword);
    if (input) {
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') submitPassword();
        });
    }
}

function closePasswordModal() {
    const modal = document.getElementById('passwordModal');
    if (modal) modal.classList.remove('active');
}

async function submitPassword() {
    const passwordInput = document.getElementById('passwordInput');
    const password = passwordInput.value;

    if (!password) {
        // Shake animation or error indication could go here
        passwordInput.style.borderColor = '#ff4757';
        setTimeout(() => passwordInput.style.borderColor = '', 500);
        return;
    }

    const btn = document.getElementById('unlockBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Verificando...';
    btn.disabled = true;

    try {
        await loadSharedAlbum(password);
    } catch (error) {
        console.error("Error submitting password:", error);
        // Reset button if error (loadSharedAlbum handles general errors, but we want to ensure button resets)
        passwordInput.value = '';
        passwordInput.focus();
    } finally {
        if (btn) {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }
}

function displayAlbum() {
    elements.loadingContainer.style.display = 'none';
    elements.albumContainer.style.display = 'block';

    elements.albumName.textContent = albumData.name;
    elements.albumDescription.textContent = albumData.description || '';
    elements.mediaCount.textContent = `${albumData.media_count} ${albumData.media_count === 1 ? 'elemento' : 'elementos'}`;
    elements.viewCount.textContent = `👁 ${albumData.views || 0} vistas`;

    displayMediaGrid();
}

function displayMediaGrid() {
    elements.mediaGrid.innerHTML = '';

    currentMedia.forEach((item, index) => {
        const mediaItem = createMediaItem(item, index);
        elements.mediaGrid.appendChild(mediaItem);
    });
}

function createMediaItem(item, index) {
    const div = document.createElement('div');
    div.className = 'media-item';
    div.dataset.index = index;

    const isVideo = item.file_type === 'video';
    const isAudio = item.file_type === 'audio';

    if (isAudio) {
        div.innerHTML = `
            <div class="audio-thumbnail">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="white">
                    <circle cx="20" cy="35" r="8"/>
                    <rect x="24" y="15" width="4" height="20" rx="2"/>
                    <ellipse cx="35" cy="15" rx="10" ry="6"/>
                </svg>
            </div>
            ${item.duration ? `<div class="media-duration">⏱ ${formatDuration(item.duration)}</div>` : ''}
        `;
    } else {
        div.innerHTML = `
            <img src="${item.thumbnail_url}" alt="" draggable="false">
            ${isVideo ? `<div class="media-duration">${item.duration ? formatDuration(item.duration) : 'VIDEO'}</div>` : ''}
        `;
    }

    div.addEventListener('click', () => openModal(index));

    return div;
}

// ============================================
// MODAL Y REPRODUCTOR
// ============================================

function openModal(index) {
    currentIndex = index;
    const item = currentMedia[currentIndex];

    elements.mediaModal.classList.add('active');
    document.body.style.overflow = 'hidden';

    displayMedia(item);
}

function closeModal() {
    // Pausar cualquier media reproduciéndose
    const video = elements.mediaDisplay.querySelector('video');
    const audio = elements.mediaDisplay.querySelector('audio');

    if (video) {
        video.pause();
        video.currentTime = 0;
    }

    if (audio) {
        audio.pause();
        audio.currentTime = 0;
    }

    elements.mediaModal.classList.remove('active');
    document.body.style.overflow = '';
}

function displayMedia(item) {
    const isVideo = item.file_type === 'video';
    const isAudio = item.file_type === 'audio';

    if (isVideo) {
        // Reproductor custom sin controles de descarga
        elements.mediaDisplay.innerHTML = `
            <video 
                src="${item.file_url}" 
                autoplay 
                ${item.file_type === 'gif' ? 'loop muted' : 'controls'}
                controlsList="nodownload"
                disablePictureInPicture
                oncontextmenu="return false;"
            >
                Tu navegador no soporta video.
            </video>
        `;
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
                        </defs>
                        <circle cx="100" cy="100" r="95" fill="url(#discGradient)"/>
                        <circle cx="100" cy="100" r="25" fill="#2d3748"/>
                        <g transform="translate(100, 100)">
                            <circle cx="-25" cy="20" r="15" fill="white"/>
                            <rect x="-18" y="-30" width="8" height="50" fill="white" rx="4"/>
                            <ellipse cx="5" cy="-30" rx="18" ry="12" fill="white"/>
                        </g>
                    </svg>
                </div>
                <div class="audio-info">
                    <h3>${item.custom_name || item.filename}</h3>
                    ${item.duration ? `<p>⏱ ${formatDuration(item.duration)}</p>` : ''}
                </div>
                <audio 
                    src="${item.file_url}" 
                    controls 
                    autoplay
                    controlsList="nodownload"
                    oncontextmenu="return false;"
                >
                    Tu navegador no soporta audio.
                </audio>
            </div>
        `;
    } else {
        elements.mediaDisplay.innerHTML = `
            <img 
                src="${item.file_url}" 
                alt="" 
                draggable="false"
                oncontextmenu="return false;"
            >
        `;
    }
}

function showPreviousMedia() {
    if (currentIndex > 0) {
        currentIndex--;
        displayMedia(currentMedia[currentIndex]);
    }
}

function showNextMedia() {
    if (currentIndex < currentMedia.length - 1) {
        currentIndex++;
        displayMedia(currentMedia[currentIndex]);
    }
}

// ============================================
// UTILIDADES
// ============================================

function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function showError(title, message) {
    elements.loadingContainer.style.display = 'none';
    elements.errorContainer.style.display = 'flex';
    elements.errorTitle.textContent = title;
    elements.errorMessage.textContent = message;
}

// ============================================
// EVENT LISTENERS
// ============================================

elements.modalClose.addEventListener('click', closeModal);
elements.modalOverlay.addEventListener('click', closeModal);
elements.navPrev.addEventListener('click', showPreviousMedia);
elements.navNext.addEventListener('click', showNextMedia);

// Teclado
document.addEventListener('keydown', (e) => {
    if (!elements.mediaModal.classList.contains('active')) return;

    if (e.key === 'Escape') closeModal();
    if (e.key === 'ArrowLeft') showPreviousMedia();
    if (e.key === 'ArrowRight') showNextMedia();
});

// ============================================
// INICIALIZACIÓN
// ============================================

loadSharedAlbum();
checkAuth();
initAuth();

// ============================================
// AUTH & UPLOAD UI
// ============================================

function initAuth() {
    // Login
    document.getElementById('loginBtn').addEventListener('click', openLoginModal);
    document.getElementById('loginClose').addEventListener('click', closeLoginModal);
    document.getElementById('doLoginBtn').addEventListener('click', performLogin);

    // Upload
    document.getElementById('uploadBtn').addEventListener('click', triggerUpload);
    document.getElementById('shareFileInput').addEventListener('change', handleFileSelect);

    // Logout
    document.getElementById('logoutBtn').addEventListener('click', async () => {
        try {
            await fetch(`${API_BASE}/auth.php?action=logout`);
            window.location.reload();
        } catch (e) {
            console.error('Logout error', e);
        }
    });

    // Login Enter Key
    document.getElementById('loginPass').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performLogin();
    });
}

function updateHeaderUI() {
    const loginBtn = document.getElementById('loginBtn');
    const userInfo = document.getElementById('userInfo');
    const userEmailDisplay = document.getElementById('userEmailDisplay');
    const uploadBtn = document.getElementById('uploadBtn');

    if (currentUser) {
        loginBtn.style.display = 'none';
        userInfo.style.display = 'flex';
        userEmailDisplay.textContent = currentUser.email || currentUser.username;
    } else {
        loginBtn.style.display = 'block';
        userInfo.style.display = 'none';
    }

    // Upload button visibility
    // Show if logged in AND permissions.can_upload is true
    const canUpload = currentUser && permissions && permissions.can_upload;

    if (canUpload) {
        uploadBtn.style.display = 'block';
    } else {
        uploadBtn.style.display = 'none';
    }
}

// Login Modal
function openLoginModal() {
    document.getElementById('loginModal').classList.add('active');
    document.getElementById('loginUser').focus();
}

function closeLoginModal() {
    document.getElementById('loginModal').classList.remove('active');
}

async function performLogin() {
    const user = document.getElementById('loginUser').value;
    const pass = document.getElementById('loginPass').value;
    const btn = document.getElementById('doLoginBtn');

    if (!user || !pass) return;

    const originalText = btn.textContent;
    btn.textContent = 'Entrando...';
    btn.disabled = true;

    try {
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: user, password: pass })
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.message || 'Error al iniciar sesión');
        }
    } catch (e) {
        console.error('Login error', e);
        alert('Error de conexión');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

// ============================================
// UPLOAD LOGIC
// ============================================

function triggerUpload() {
    document.getElementById('shareFileInput').click();
}

async function handleFileSelect(e) {
    const files = e.target.files;
    if (!files.length) return;

    uploadFiles(files);
    // Clear input so same files can be selected again if needed
    e.target.value = '';
}

async function uploadFiles(files) {
    const progressDiv = document.getElementById('uploadProgress');
    const nameSpan = document.getElementById('uploadFileName');
    const percentSpan = document.getElementById('uploadPercent');
    const fill = document.getElementById('progressFill');

    progressDiv.style.display = 'block';

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        nameSpan.textContent = `Subiendo ${file.name} (${i + 1}/${files.length})`;
        percentSpan.textContent = '0%';
        fill.style.width = '0%';

        try {
            await uploadToShare(file, (percent) => {
                percentSpan.textContent = `${percent}%`;
                fill.style.width = `${percent}%`;
            });
        } catch (e) {
            console.error('Upload error', e);
            alert(`Error al subir ${file.name}: ${e.message}`);
        }
    }

    setTimeout(() => {
        progressDiv.style.display = 'none';
        // Reload media
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        // We handle password via closure or we just reload page?
        // Reloading page is safest but disrupts UX.
        // Better to re-fetch media.
        // We can reuse loadSharedAlbum, but we might need password if it was private.
        // If we unlocked it, session cookies should handle it? 
        // Or we stored password in `loadSharedAlbum` local scope?
        // Actually `loadSharedAlbum` uses `password` param only for first unlock.
        // `api/share.php` likely checks session for unlock status?
        // Let's check `ShareController`.
        // `checkPrivateAccess` uses `$_SESSION['private_access_' . $albumId]`.
        // So subsequent calls don't need password.

        loadSharedAlbum();
    }, 1000);
}

function uploadToShare(file, onProgress) {
    return new Promise((resolve, reject) => {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');

        const formData = new FormData();
        formData.append('file', file);
        formData.append('token', token);
        if (currentPassword) {
            formData.append('password', currentPassword);
        }

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                onProgress(percent);
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status === 200 || xhr.status === 201) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        resolve(result);
                    } else {
                        reject(new Error(result.message || 'Error desconocido'));
                    }
                } catch (e) {
                    reject(new Error('Respuesta inválida del servidor'));
                }
            } else {
                reject(new Error(`HTTP Error ${xhr.status}`));
            }
        });

        xhr.addEventListener('error', () => {
            reject(new Error('Error de red'));
        });

        xhr.open('POST', `${API_BASE}/share_upload.php`);
        xhr.send(formData);
    });
}
