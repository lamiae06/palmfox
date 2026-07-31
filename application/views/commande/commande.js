const activeLink = document.querySelector('.sidebar-nav a[href*="commande.php"]');
if (activeLink) {
    activeLink.classList.add('active');
}
const $ = (sel) => document.querySelector(sel);

let currentOrderProducts = []; // Contient le panier virtuel [{id_produit, reference, quantite}]
let pendingDeleteId = null;

// ========================================================
// 1. DESSINER LE PANIER DU FORMULAIRE (DANS LA MODAL)
// ========================================================
function renderModalProducts() {
  const tbody = $("#modalProductsTableBody");
  
  if (currentOrderProducts.length === 0) {
    tbody.innerHTML = `<tr><td colspan="3" style="text-align: center; padding: 12px; color: #9aa2b1; font-style: italic;">Aucun produit ajouté</td></tr>`;
    return;
  }

  tbody.innerHTML = currentOrderProducts.map((p, index) => `
    <tr style="border-bottom: 1px solid #f0f1f5;">
      <td style="padding: 8px; color: #333a47;">${p.reference}</td>
      <td style="padding: 8px; text-align: center;"><strong>${p.quantite}</strong></td>
      <td style="padding: 8px; text-align: center;">
        <button type="button" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 13px;" onclick="removeProductFromModal(${index})">
          <i class="fa-solid fa-trash-can"></i>
        </button>
      </td>
    </tr>
  `).join("");
}

// Fonction globale pour supprimer un produit du panier
window.removeProductFromModal = function(index) {
  currentOrderProducts.splice(index, 1);
  renderModalProducts();
};

// Affichage dynamique des informations de stocks
function updateStockInfo() {
  const select = $("#produitSelect");
  const textInfo = $("#stockInfoText");
  
  if (!select.value) {
    textInfo.textContent = "Sélectionnez un produit pour voir son stock.";
    textInfo.style.color = "#8a94a6";
    return;
  }
  
  const option = select.options[select.selectedIndex];
  const stock = parseInt(option.dataset.stock, 10);

  if (stock > 0) {
    textInfo.textContent = `Stock disponible : ${stock} unité(s).`;
    textInfo.style.color = "#1e824c";
  } else {
    textInfo.textContent = `Rupture de stock — ajout impossible.`;
    textInfo.style.color = "#c62828";
  }
}

// AJOUTER UNE LIGNE PRODUIT AU PANIER VIRTUEL (Déclenché par le bouton "+")
function addProductToModalList() {
  const select = $("#produitSelect");
  const qteInput = $("#quantiteInput");
  
  const id_produit = select.value;
  const quantite = parseInt(qteInput.value, 10);

  if (!id_produit || isNaN(quantite) || quantite <= 0) {
    alert("Veuillez choisir un produit et renseigner une quantité valide.");
    return;
  }

  const option = select.options[select.selectedIndex];
  const reference = option.dataset.ref;
  const stockMax = parseInt(option.dataset.stock, 10);

  // Vérification de cumul dans la modale
  const existing = currentOrderProducts.find(p => p.id_produit === id_produit);
  const totalRequested = existing ? (existing.quantite + quantite) : quantite;

  if (totalRequested > stockMax) {
    alert(`Stock insuffisant. Vous demandez un total de ${totalRequested} articles alors qu'il n'en reste que ${stockMax} en stock.`);
    return;
  }

  if (existing) {
    existing.quantite = totalRequested;
  } else {
    currentOrderProducts.push({ id_produit, reference, quantite });
  }

  // Nettoyage
  qteInput.value = "";
  select.value = "";
  updateStockInfo();
  renderModalProducts();
}

// ========================================================
// 2. OUVERTURE / FERMETURE DE LA FENÊTRE MODALE
// ========================================================
function openModal(mode, id = null) {
  const modal = $("#commandeModal");
  $("#commandeForm").reset();
  $("#formError").textContent = "";
  currentOrderProducts = []; 

  if (mode === "edit" && id) {
    $("#commandeId").value = id;
    $("#modalTitle").textContent = `Modifier la Commande CMD${String(id).padStart(3, '0')}`;
    
    // Récupération AJAX des lignes depuis la BDD
    fetch(`get_commande_details.php?id=${id}`)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          $("#clientSelect").value = data.commande.id_client;
          $("#delaiInput").value = data.commande.delai;
          $("#dateLivraisonInput").value = data.commande.date_livraison;
          $("#statutSelect").value = data.commande.statut;
          currentOrderProducts = data.produits;
          renderModalProducts();
        }
      });
  } else {
    $("#commandeId").value = "";
    $("#modalTitle").textContent = "Ajouter une Nouvelle Commande";
    renderModalProducts();
  }

  updateStockInfo();
  modal.classList.add("open");
}

function closeModal() { $("#commandeModal").classList.remove("open"); }
function closeDeleteModal() { $("#deleteModal").classList.remove("open"); pendingDeleteId = null; }

// ========================================================
// 3. ACTIONS DE TRAITEMENT (SOUMISSION FORMULAIRE & AJAX)
// ========================================================
function handleSubmit(e) {
  e.preventDefault();

  if (currentOrderProducts.length === 0) {
    $("#formError").textContent = "Veuillez ajouter au moins un produit à cette commande.";
    return;
  }

  const statut = $("#statutSelect").value;

  const payload = {
    action: "save",
    id_commande: $("#commandeId").value,
    id_client: $("#clientSelect").value,
    delai: $("#delaiInput").value.trim(),
    date_livraison: $("#dateLivraisonInput").value,
    statut: statut,
    produits: currentOrderProducts
  };

  fetch("process_commande.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      
      // ============================================================
      // RECTIFICATION DU STOCK DANS L'INTERFACE EN TEMPS RÉEL
      // ============================================================
      // Si la commande est validée (Confirmée ou Livrée), on met à jour les <option>
      if (statut === "Confirmée" || statut === "Livrée") {
        currentOrderProducts.forEach(prod => {
          // On cherche l'option correspondante au produit dans le select
          const option = $(`#produitSelect option[value="${prod.id_produit}"]`);
          if (option) {
            // 1. On récupère le stock actuel stocké dans le HTML
            let currentStock = parseInt(option.dataset.stock, 10);
            // 2. On soustrait la quantité commandée
            let newStock = currentStock - prod.quantite;
            if (newStock < 0) newStock = 0; // Sécurité pour ne pas afficher de stock négatif

            // 3. On met à jour l'attribut data-stock pour les prochains calculs
            option.dataset.stock = newStock;

            // 4. On met à jour le texte visible dans le menu déroulant
            const reference = option.dataset.ref;
            option.textContent = `${reference} (Stock: ${newStock})`;
          }
        });
      }

      // Au lieu de recharger brutalement la page (window.location.reload()),
      // on ferme juste la modale et on peut rafraîchir le tableau ou laisser l'utilisateur continuer !
      closeModal();
      
      // Optionnel : Si vous voulez quand même rafraîchir le grand tableau principal pour voir la nouvelle ligne,
      // vous pouvez laisser la ligne suivante. Mais si vous l'enlevez, l'utilisateur reste sur la page sans coupure 
      // et le stock du select est parfaitement mis à jour pour la commande suivante !
      window.location.reload(); 

    } else {
      $("#formError").textContent = data.message || "Une erreur s'est produite.";
    }
  });
}

function handleTableClick(e) {
  const btn = e.target.closest("button[data-action]");
  if (!btn) return;

  const id = btn.dataset.id;
  if (btn.dataset.action === "edit") {
    openModal("edit", id);
  } else if (btn.dataset.action === "delete") {
    pendingDeleteId = id;
    $("#deleteCmdId").textContent = `CMD${String(id).padStart(3, '0')}`;
    $("#deleteModal").classList.add("open");
  }
}

function confirmDelete() {
  if (!pendingDeleteId) return;
  fetch("process_commande.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "delete", id_commande: pendingDeleteId })
  })
  .then(res => res.json())
  .then(data => { if (data.success) window.location.reload(); });
}

// Recherche textuelle en direct
function filterTable(keyword) {
  const rows = document.querySelectorAll("#commandesTableBody tr");
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(keyword.toLowerCase().trim()) ? "" : "none";
  });
}

// ========================================================
// 4. ATTACHEMENT DES ÉCOUTEURS
// ========================================================
document.addEventListener("DOMContentLoaded", () => {
  $("#btnOpenAddCommande").addEventListener("click", () => openModal("add"));
  $("#btnCloseModal").addEventListener("click", closeModal);
  $("#btnCancelModal").addEventListener("click", closeModal);
  
  // ÉCOUTE DU SÉLECTEUR ET DU BOUTON "+"
  $("#produitSelect").addEventListener("change", updateStockInfo);
  $("#btnAddProductRow").addEventListener("click", addProductToModalList);
  
  $("#commandeForm").addEventListener("submit", handleSubmit);
  $("#commandesTableBody").addEventListener("click", handleTableClick);
  $("#btnCancelDelete").addEventListener("click", closeDeleteModal);
  $("#btnConfirmDelete").addEventListener("click", confirmDelete);
  $("#searchInput").addEventListener("input", (e) => filterTable(e.target.value));
});