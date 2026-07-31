
document.addEventListener("DOMContentLoaded", function () {
  const activeLink = document.querySelector('.sidebar-nav a[href*="clients.php"]');
if (activeLink) {
    activeLink.classList.add('active');
}
  /* ===== Éléments du DOM ===== */
  const modalClient = document.getElementById("clientModal");
  const modalFilter = document.getElementById("filterModal");
  const modalDelete = document.getElementById("deleteModal");

  const btnAddClient = document.getElementById("btnAddClient");
  const closeModalBtn = document.getElementById("closeModal");
  const btnFilter = document.getElementById("btnFilter");
  const closeFilterBtn = document.getElementById("closeFilter");
  const cancelFilterBtn = document.getElementById("cancelFilter");
  
  const btnAddContact = document.getElementById("btnAddContact");
  const contactsContainer = document.getElementById("contactsContainer");

  const deleteClientId = document.getElementById("deleteClientId");
  const deleteClientName = document.getElementById("deleteClientName");
  const cancelDeleteBtn = document.getElementById("cancelDelete");

  /* ===== Gestion de l'affichage des Fenêtres Modales ===== */

  if (btnAddClient) {
    btnAddClient.addEventListener("click", function () {
      // Nettoyage initial en cas d'ajout d'un nouveau client pur
      document.getElementById("clientId").value = "";
      document.getElementById("clientForm").reset();
      document.getElementById("modalTitle").textContent = "Ajouter un Client";
      
      // On remet une structure de contact vide par défaut
      contactsContainer.innerHTML = `
        <div class="contact-item">
            <div class="form-group">
                <label>Nom du contact</label>
                <input type="text" name="contacts[nom][]">
            </div>
            <div class="form-group">
                <label>Téléphone du contact</label>
                <input type="text" name="contacts[telephone][]">
            </div>
            <div class="form-group">
                <label>Email du contact</label>
                <input type="email" name="contacts[email][]">
            </div>
            <button type="button" class="btn-remove-contact" title="Supprimer ce contact" onclick="this.parentElement.remove();">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>`;
      
      modalClient.classList.add("active");
    });
  }

  if (closeModalBtn) {
    closeModalBtn.addEventListener("click", function () {
      modalClient.classList.remove("active");
      // Si la page contenait des paramètres d'édition (?edit=x), on nettoie l'URL
      if (window.location.search.indexOf("edit=") !== -1) {
         window.location.href = "clients.php";
      }
    });
  }

  if (btnFilter) {
    btnFilter.addEventListener("click", function () {
      modalFilter.classList.add("active");
    });
  }

  if (closeFilterBtn) {
    closeFilterBtn.addEventListener("click", function () {
      modalFilter.classList.remove("active");
    });
  }

  if (cancelFilterBtn) {
    cancelFilterBtn.addEventListener("click", function () {
      modalFilter.classList.remove("active");
    });
  }

  /* ===== Gestion Dynamique des Lignes de Contacts ===== */

  if (btnAddContact) {
    btnAddContact.addEventListener("click", function () {
      const row = document.createElement("div");
      row.className = "contact-item";
      
      // Utilisation des crochets [] natifs de PHP pour réceptionner un tableau ordonné
      row.innerHTML = `
          <div class="form-group">
              <label>Nom du contact</label>
              <input type="text" name="contacts[nom][]">
          </div>
          <div class="form-group">
              <label>Téléphone du contact</label>
              <input type="text" name="contacts[telephone][]">
          </div>
          <div class="form-group">
              <label>Email du contact</label>
              <input type="email" name="contacts[email][]">
          </div>
          <button type="button" class="btn-remove-contact" title="Supprimer ce contact">
              <i class="fa-solid fa-trash"></i>
          </button>
      `;

      row.querySelector(".btn-remove-contact").addEventListener("click", function () {
        row.remove();
      });

      contactsContainer.appendChild(row);
    });
  }

  /* ===== Gestion de la Modale de Confirmation de Suppression ===== */

  // On écoute les clics sur les icônes de corbeille générées dans le tableau PHP
  document.querySelectorAll(".btn-trigger-delete").forEach(function (button) {
    button.addEventListener("click", function () {
      const id = this.getAttribute("data-id");
      const nom = this.getAttribute("data-nom");

      deleteClientId.value = id;
      deleteClientName.textContent = nom;
      modalDelete.classList.add("active");
    });
  });

  if (cancelDeleteBtn) {
    cancelDeleteBtn.addEventListener("click", function () {
      modalDelete.classList.remove("active");
    });
  }

  // Fermeture globale des modales lors d'un clic à l'extérieur
  window.addEventListener("click", function (e) {
    if (e.target === modalClient) {
        modalClient.classList.remove("active");
        if (window.location.search.indexOf("edit=") !== -1) window.location.href = "clients.php";
    }
    if (e.target === modalFilter) modalFilter.classList.remove("active");
    if (e.target === modalDelete) modalDelete.classList.remove("active");
  });

  // Fermeture avec la touche Échap
  window.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      modalClient.classList.remove("active");
      modalFilter.classList.remove("active");
      modalDelete.classList.remove("active");
      if (window.location.search.indexOf("edit=") !== -1) window.location.href = "clients.php";
    }
  });
});