<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diseño con Transiciones</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    @vite('resources/css/noticias.css')
    @vite('resources/js/scripts.js')
    <!-- Cargar Font Awesome para los iconos -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
   
</head>
<body>
  <header class="navbar">
    <div class="navbar-left">
      <a href="#">
        <img src="{{ asset('img/DevPlay logo.png') }}" alt="Logo" class="logo-image">
      </a>
    </div>
    <nav class="navbar-center nav-links">
      <a href="{{ route('rutaInicio') }}">Inicio</a>
      <a href="/donar">Donativos</a>
      <a href="{{ route('rutaNosotros') }}">Nosotros</a>
    </nav>
    <div class="navbar-right auth-buttons">
      @if (session('logged_in'))
        <div class="user-info">
          <span class="username" onclick="toggleLogoutDropdown()">{{ session('username') }}</span>
          <div id="logoutDropdown" class="logout-dropdown">
            <button onclick="window.location.href='{{ route('rutaLogout') }}'">Cerrar sesión</button>
          </div>
        </div>
      @else
        <button class="login-btn" onclick="window.location.href='{{ route('rutaLogin') }}'">Iniciar Sesión</button>
      @endif
      <button class="news-btn" onclick="window.location.href='{{ route('rutaNoticias') }}'">Noticias</button>
    </div>
    
  </header>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  <!-- Modal y overlay para comentarios -->
  <div id="modalOverlay"></div>
  <div id="commentsModal">
    <div class="modal-header">
      <h2>Comentarios</h2>
      <button id="modalCloseBtn">×</button>
    </div>
    <div id="modalCommentsList"></div>
    <textarea id="modalNewComment" placeholder="Escribe tu comentario" style="width:100%; height: 80px;"></textarea>
    <button id="modalPostCommentBtn">Publicar comentario</button>
  </div>

  <div class="news-container">
    @foreach($news as $newsItem)
      <div class="main-card appear-on-load" data-post-id="{{ $newsItem['id'] }}">
        <div class="text-content">
          <h1 class="news-title">{{ $newsItem['title'] }}</h1>
          <p class="news-description">{{ $newsItem['description'] }}</p>
        </div>
        @if(isset($newsItem['image']) && $newsItem['image'])
          <img class="news-image" src="data:image/jpeg;base64,{{ $newsItem['image'] }}" alt="Imagen de la noticia">
        @endif
        <div class="news-actions">
          <button class="like-btn" data-post-id="{{ $newsItem['id'] }}">
            <i class="fas fa-thumbs-up"></i> <span class="like-count">{{ $newsItem['likes'] }}</span>
          </button>
          <button class="dislike-btn" data-post-id="{{ $newsItem['id'] }}">
            <i class="fas fa-thumbs-down"></i> <span class="dislike-count">{{ $newsItem['dislikes'] }}</span>
          </button>
          <button class="toggle-comments-btn">
            <i class="fas fa-comment-dots"></i> Ver comentarios
          </button>
        </div>
        <div class="comments" style="display: none;">
          <div class="comments-list">
            <!-- Los comentarios se cargarán aquí si se usan en la card -->
          </div>
          <textarea class="new-comment" placeholder="Escribe un comentario"></textarea>
          <button class="post-comment-btn" data-post-id="{{ $newsItem['id'] }}">Publicar comentario</button>
        </div>
      </div>
    @endforeach
  </div>

  <footer class="footer">
    <div class="footer-content">
      <a href="#">Política de Privacidad</a> | 
      <a href="#">Términos y Condiciones</a> | 
      <a href="#">Contacto</a>
    </div>
    <p>&copy; 2024 Sustainity. Todos los derechos reservados.</p>
  </footer>


  <!-- Script para likes/dislikes y comentarios -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const baseUrl = "{{ url('/') }}"; // Cambiar a URL base de Laravel
      const userId = "{{ session('user_id') ?? 'null' }}";
      if (userId === "null") {
        console.warn("El usuario no está logueado; user_id es null.");
      }

      // Funciones para mostrar y ocultar el modal de carga
      function showLoading() {
        document.getElementById("loadingModal").style.display = "flex";
      }
      function hideLoading() {
        document.getElementById("loadingModal").style.display = "none";
      }

      // --- Likes / Dislikes ---
      async function sendRequest(method, url, bodyData) {
        try {
          const response = await fetch(url, {
            method: method,
            headers: { 
              "Content-Type": "application/x-www-form-urlencoded",
              "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: bodyData
          });
          return await response.json();
        } catch (error) {
          console.error("Error en request:", error);
          throw error;
        }
      }

      async function handleLike(button) {
        const postId = button.getAttribute("data-post-id");
        if (!postId) return;
        const bodyData = `user_id=${userId}`;
        const dislikeButton = document.querySelector(`.dislike-btn[data-post-id="${postId}"]`);

        showLoading();
        try {
          if (button.classList.contains("active")) {
            button.classList.remove("active");
            let likeCountElem = button.querySelector(".like-count");
            likeCountElem.textContent = parseInt(likeCountElem.textContent) - 1;
            await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/like`, bodyData);
            console.log("Like eliminado");
          } else {
            if (dislikeButton && dislikeButton.classList.contains("active")) {
              dislikeButton.classList.remove("active");
              let dislikeCountElem = dislikeButton.querySelector(".dislike-count");
              dislikeCountElem.textContent = parseInt(dislikeCountElem.textContent) - 1;
              await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/dislike`, bodyData);
              console.log("Dislike eliminado para dar like");
            }
            button.classList.add("active");
            let likeCountElem = button.querySelector(".like-count");
            likeCountElem.textContent = parseInt(likeCountElem.textContent) + 1;
            await sendRequest("POST", `${baseUrl}/news/posts/${postId}/like`, bodyData);
            console.log("Like registrado");
          }
        } catch (error) {
          console.error("Error en handleLike:", error);
        } finally {
          hideLoading();
        }
      }

      async function handleDislike(button) {
        const postId = button.getAttribute("data-post-id");
        if (!postId) return;
        const bodyData = `user_id=${userId}`;
        const likeButton = document.querySelector(`.like-btn[data-post-id="${postId}"]`);

        showLoading();
        try {
          if (button.classList.contains("active")) {
            button.classList.remove("active");
            let dislikeCountElem = button.querySelector(".dislike-count");
            dislikeCountElem.textContent = parseInt(dislikeCountElem.textContent) - 1;
            await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/dislike`, bodyData);
            console.log("Dislike eliminado");
          } else {
            if (likeButton && likeButton.classList.contains("active")) {
              likeButton.classList.remove("active");
              let likeCountElem = likeButton.querySelector(".like-count");
              likeCountElem.textContent = parseInt(likeCountElem.textContent) - 1;
              await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/like`, bodyData);
              console.log("Like eliminado para dar dislike");
            }
            button.classList.add("active");
            let dislikeCountElem = button.querySelector(".dislike-count");
            dislikeCountElem.textContent = parseInt(dislikeCountElem.textContent) + 1;
            await sendRequest("POST", `${baseUrl}/news/posts/${postId}/dislike`, bodyData);
            console.log("Dislike registrado");
          }
        } catch (error) {
          console.error("Error en handleDislike:", error);
        } finally {
          hideLoading();
        }
      }

      document.querySelectorAll(".like-btn").forEach(button => {
        button.addEventListener("click", () => {
          if (userId === "null") {
            alert("Debes estar logueado para dar like.");
            return;
          }
          handleLike(button);
        });
      });
      
      document.querySelectorAll(".dislike-btn").forEach(button => {
        button.addEventListener("click", () => {
          if (userId === "null") {
            alert("Debes estar logueado para dar dislike.");
            return;
          }
          handleDislike(button);
        });
      });

      // --- Modal de Comentarios ---
      function openCommentsModal(postId) {
        document.getElementById("commentsModal").dataset.postId = postId;
        document.getElementById("commentsModal").style.display = "block";
        document.getElementById("modalOverlay").style.display = "block";
        loadComments(postId);
      }

      function closeCommentsModal() {
        document.getElementById("commentsModal").style.display = "none";
        document.getElementById("modalOverlay").style.display = "none";
      }

      async function loadComments(postId) {
        try {
          const response = await fetch(`${baseUrl}/news/posts/${postId}/comments`);
          const comments = await response.json();
          const list = document.getElementById("modalCommentsList");
          list.innerHTML = "";
          comments.forEach(comment => {
            const commentDiv = document.createElement("div");
            commentDiv.classList.add("comment-item");
            commentDiv.style.position = "relative";

            const createdAt = new Date(comment.created_at);
            const formattedDate = createdAt.toLocaleDateString('es-ES', { 
                day: '2-digit', month: 'short', year: 'numeric' 
              }) + ', ' +
              createdAt.toLocaleTimeString('es-ES', { 
                hour: '2-digit', minute: '2-digit', hour12: false, hourCycle: 'h23', timeZone: 'UTC' 
              });
            
            commentDiv.innerHTML = `
              <p>${comment.description}</p>
              <small>Por: ${comment.user.username || comment.user.id} ${comment.created_at ? "- " + formattedDate : ""}</small>
            `;
            
            if (parseInt(comment.user.id) === parseInt(userId)) {
              const optionsBtn = document.createElement("span");
              optionsBtn.textContent = "⋮";
              optionsBtn.style.cursor = "pointer";
              optionsBtn.style.position = "absolute";
              optionsBtn.style.top = "5px";
              optionsBtn.style.right = "5px";
              optionsBtn.style.fontSize = "1.5em";
              optionsBtn.style.color = "#3f51b5";

              const optionsMenu = document.createElement("div");
              optionsMenu.style.display = "none";
              optionsMenu.style.position = "absolute";
              optionsMenu.style.top = "30px";
              optionsMenu.style.right = "5px";
              optionsMenu.style.background = "white";
              optionsMenu.style.padding = "8px";
              optionsMenu.style.borderRadius = "5px";
              optionsMenu.style.zIndex = "10";
              
              const editBtn = document.createElement("button");
              editBtn.textContent = "Editar";
              editBtn.style.fontSize = "0.9em";
              editBtn.style.padding = "8px 12px";
              editBtn.style.marginBottom = "5px";
              editBtn.style.backgroundColor = "#ffa500";
              editBtn.style.border = "none";
              editBtn.style.borderRadius = "4px";
              editBtn.style.cursor = "pointer";
              editBtn.addEventListener("click", () => {
                const editArea = document.createElement("textarea");
                editArea.value = comment.description;
                editArea.classList.add("comment-edit-textarea");
                const saveBtn = document.createElement("button");
                saveBtn.textContent = "Guardar";
                saveBtn.style.fontSize = "0.9em";
                saveBtn.style.padding = "8px 12px";
                saveBtn.style.backgroundColor = "#4CAF50";
                saveBtn.style.border = "none";
                saveBtn.style.borderRadius = "4px";
                saveBtn.style.cursor = "pointer";
                saveBtn.addEventListener("click", async () => {
                  try {
                    const res = await fetch(`${baseUrl}/news/comments/${comment.id}`, {
                      method: "PUT",
                      headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                      },
                      body: JSON.stringify({ description: editArea.value })
                    });
                    const result = await res.json();
                    if (result.message) { loadComments(postId); }
                  } catch (err) { console.error(err); }
                });
                commentDiv.innerHTML = "";
                commentDiv.appendChild(editArea);
                commentDiv.appendChild(saveBtn);
              });
              
              const deleteBtn = document.createElement("button");
              deleteBtn.textContent = "Eliminar";
              deleteBtn.style.fontSize = "0.9em";
              deleteBtn.style.padding = "8px 12px";
              deleteBtn.style.backgroundColor = "#F44336";
              deleteBtn.style.border = "none";
              deleteBtn.style.borderRadius = "4px";
              deleteBtn.style.cursor = "pointer";
              deleteBtn.addEventListener("click", async () => {
                if (confirm("¿Estás seguro de eliminar este comentario?")) {
                  try {
                    const res = await fetch(`${baseUrl}/news/comments/${comment.id}`, {
                      method: "DELETE",
                      headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                      }
                    });
                    const result = await res.json();
                    if (result.message) { loadComments(postId); }
                  } catch (err) { console.error(err); }
                }
              });
              
              optionsMenu.appendChild(editBtn);
              optionsMenu.appendChild(deleteBtn);
              
              optionsBtn.addEventListener("click", () => {
                optionsMenu.style.display = optionsMenu.style.display === "block" ? "none" : "block";
              });
              
              commentDiv.appendChild(optionsBtn);
              commentDiv.appendChild(optionsMenu);
            }
            
            list.appendChild(commentDiv);
          });
        } catch (error) {
          console.error("Error cargando comentarios:", error);
        }
      }

      async function postComment(postId) {
        const commentText = document.getElementById("modalNewComment").value;
        const submitBtn = document.getElementById("modalPostCommentBtn");
        
        // Validaciones del lado del cliente
        if (!commentText.trim()) {
          alert("El comentario no puede estar vacío");
          return;
        }
        
        if (commentText.trim().length < 3) {
          alert("El comentario debe tener al menos 3 caracteres");
          return;
        }
        
        if (commentText.length > 500) {
          alert("El comentario no puede exceder 500 caracteres");
          return;
        }
        
        // Deshabilitar botón para prevenir múltiples envíos
        submitBtn.disabled = true;
        submitBtn.textContent = "Publicando...";
        
        try {
          const res = await fetch(`${baseUrl}/news/posts/${postId}/comments`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ user_id: Number(userId), description: commentText.trim() })
          });
          
          const result = await res.json();
          
          if (res.ok && result.message) {
            document.getElementById("modalNewComment").value = "";
            loadComments(postId);
            
            // Mostrar mensaje de éxito
            showSuccessMessage("Comentario publicado exitosamente");
            
            // Cooldown visual - deshabilitar por 30 segundos
            startCommentCooldown(submitBtn);
          } else {
            // Mostrar error específico
            alert(result.error || "Error al publicar el comentario");
            submitBtn.disabled = false;
            submitBtn.textContent = "Publicar comentario";
          }
        } catch (error) {
          console.error("Error publicando comentario:", error);
          alert("Error de conexión. Intenta nuevamente.");
          submitBtn.disabled = false;
          submitBtn.textContent = "Publicar comentario";
        }
      }
      
      // Función para mostrar cooldown visual
      function startCommentCooldown(button) {
        let countdown = 30;
        button.disabled = true;
        
        const interval = setInterval(() => {
          button.textContent = `Espera ${countdown}s`;
          countdown--;
          
          if (countdown < 0) {
            clearInterval(interval);
            button.disabled = false;
            button.textContent = "Publicar comentario";
          }
        }, 1000);
      }
      
      // Función para mostrar mensajes de éxito
      function showSuccessMessage(message) {
        const successDiv = document.createElement("div");
        successDiv.style.cssText = `
          position: fixed;
          top: 100px;
          left: 50%;
          transform: translateX(-50%);
          background-color: #d4edda;
          color: #155724;
          padding: 10px 20px;
          border: 1px solid #c3e6cb;
          border-radius: 5px;
          z-index: 3000;
          font-family: 'Press Start 2P', cursive;
          font-size: 0.7em;
        `;
        successDiv.textContent = message;
        
        document.body.appendChild(successDiv);
        
        setTimeout(() => {
          successDiv.remove();
        }, 3000);
      }
      
      // Contador de caracteres para el textarea
      const modalNewComment = document.getElementById("modalNewComment");
      if (modalNewComment) {
        // Crear contador de caracteres
        const charCounter = document.createElement("div");
        charCounter.style.cssText = `
          font-size: 0.7em;
          color: #ffcb05;
          text-align: right;
          margin-top: 5px;
          font-family: 'Press Start 2P', cursive;
        `;
        charCounter.textContent = "0/500";
        modalNewComment.parentNode.insertBefore(charCounter, modalNewComment.nextSibling);
        
        modalNewComment.addEventListener("input", function() {
          const length = this.value.length;
          charCounter.textContent = `${length}/500`;
          
          if (length > 500) {
            charCounter.style.color = "#ff5722";
          } else if (length > 400) {
            charCounter.style.color = "#ffa500";
          } else {
            charCounter.style.color = "#ffcb05";
          }
        });
      }

      document.querySelectorAll(".toggle-comments-btn").forEach(button => {
        button.addEventListener("click", function() {
          const postId = this.closest(".main-card").dataset.postId;
          if (!postId) return;
          openCommentsModal(postId);
        });
      });

      document.getElementById("modalPostCommentBtn").addEventListener("click", function() {
        const postId = document.getElementById("commentsModal").dataset.postId;
        postComment(postId);
      });

      document.getElementById("modalCloseBtn").addEventListener("click", closeCommentsModal);
    });
  </script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>

  <!-- Al final del body, fuera del header -->
  <div id="loadingModal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #fff; padding: 20px; border-radius: 5px; text-align: center; font-family: 'Press Start 2P', cursive;">
      <p class="loading-text">Cargando...</p>
    </div>
  </div>
</body>
</html>