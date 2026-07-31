//   CONFIGURATION CONFIG ET PREPARATION PDF.JS

// Indique à PDF.js où trouver son moteur d'exécution (Worker)
const activeLink = document.querySelector('.sidebar-nav a[href*="produits.php"]');
    if (activeLink) {
        activeLink.classList.add('active');
    }
if (typeof pdfjsLib !== 'undefined') {
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
}

function toggleFilterOptions() {
  const panel = document.getElementById("filterPanel");
  if (panel) {
    panel.classList.toggle("active");
  }
}


function setupEditModal(button) {
  const id = button.getAttribute("data-id");
  const code = button.getAttribute("data-code");
  const ref = button.getAttribute("data-reference");
  const desc = button.getAttribute("data-description");
  const qty = button.getAttribute("data-stock");
  const image_pdf = button.getAttribute("data-fichier");

  openModal("edit", id, code, ref, desc, qty, image_pdf);
}

function openModal(mode, id="", code = "", ref = "", desc = "", qty = 0, image_pdf="") {
  const modal = document.getElementById("productModal");
  const title = document.getElementById("modalTitle");
  const submitBtn = document.getElementById("formSubmitBtn");

  if (!modal) return; 
  if (mode === "edit") {
    title.innerText = "Modifier le Produit";
    submitBtn.innerText = "Sauvegarder les modifications";
    
    document.getElementById("produit_id").value = id;
    document.getElementById("formCode").value = code;
    document.getElementById("formReference").value = ref;
    
    if (desc && desc.trim() !== "") {
      document.getElementById("formDescription").value = desc;
    } else {
      document.getElementById("formDescription").value = "";
    }
    
    document.getElementById("formQty").value = qty;
    
  } else {
    title.innerText = "Ajouter un Nouveau Produit";
    submitBtn.innerText = "Enregistrer le produit";
    
    document.getElementById("produit_id").value = "";
    document.getElementById("formCode").value = "";
    document.getElementById("formReference").value = "";
    document.getElementById("formDescription").value = "";
    document.getElementById("formQty").value = 0;
  }
  modal.classList.add("active");
}

function closeModal() {
  const modal = document.getElementById("productModal");
  if (modal) {
    modal.classList.remove("active");
  }
}

function openDeleteModal(id, productName) {
  const nameElem = document.getElementById("deleteProductName");
  const idInput = document.getElementById("produit_id_delete");
  const modal = document.getElementById("deleteModal");

  if (nameElem) nameElem.innerText = productName;
  if (idInput) idInput.value = id; 
  if (modal) modal.classList.add("active");
}

function closeDeleteModal() {
  const modal = document.getElementById("deleteModal");
  if (modal) {
    modal.classList.remove("active");
  }
}


//   LOGIQUE : RECHERCHE, FILTRE LIVE ET REPO-PDF
document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.querySelector(".search-box input");
  const filterStock = document.getElementById("filterStock");
  const btnApplyFiltre = document.getElementById("btnApplyFiltre");
  const tableRows = document.querySelectorAll(".data-table tbody tr");

  
  if (tableRows.length === 0 || (tableRows.length === 1 && tableRows[0].cells.length === 1)) return;

  function runFilter() {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const stockStatus = filterStock ? filterStock.value : "all";

    tableRows.forEach(row => {
      const editBtn = row.querySelector(".btn-edit");
      if (!editBtn) return;

      const code = (editBtn.getAttribute("data-code") || "").toLowerCase();
      const reference = (editBtn.getAttribute("data-reference") || "").toLowerCase();
      const description = (editBtn.getAttribute("data-description") || "").toLowerCase();
      const stock = parseInt(editBtn.getAttribute("data-stock"), 10) || 0;

      // 1. Recherche textuelle
      const matchesSearch = code.includes(query) || reference.includes(query) || description.includes(query);

      // 2. Filtre sur l'état du stock
      let matchesStock = false;
      if (stockStatus === "all") {
        matchesStock = true;
      } else if (stockStatus === "instock") {
        matchesStock = stock > 5;
      } else if (stockStatus === "lowstock") {
        matchesStock = stock > 0 && stock <= 5;
      } else if (stockStatus === "out") {
        matchesStock = stock <= 0;
      }

      // Appliquer la visibilité sur la ligne
      if (matchesSearch && matchesStock) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  }

  // Recherche automatique en direct dès qu'on tape au clavier
  if (searchInput) {
    searchInput.addEventListener("input", runFilter);
  }

  // Application du filtre au clic sur le bouton du volet
  if (btnApplyFiltre) {
    btnApplyFiltre.addEventListener("click", (e) => {
      e.preventDefault();
      runFilter();
      toggleFilterOptions(); // Ferme automatiquement le volet de filtre
    });
  }
});


//LOGIQUE D'EXTRACTION DE LA PREMIERE PAGE PDF
window.addEventListener("load", () => {
  // S'assurer que la bibliothèque PDF.js est bien chargée sur la page
  if (typeof pdfjsLib === 'undefined') return;

  const pdfCanvases = document.querySelectorAll(".pdf-thumbnail");

  pdfCanvases.forEach(canvas => {
    const url = canvas.getAttribute("data-pdf-url");
    if (!url) return;

    // Charger le fichier PDF de façon asynchrone depuis le dossier uploads
    pdfjsLib.getDocument(url).promise.then(pdf => {
      // Récupérer uniquement la première page du document
      return pdf.getPage(1);
    }).then(page => {
      const context = canvas.getContext('2d');
      
      // Définir un facteur de zoom adapté pour notre petite miniature du tableau
      const viewport = page.getViewport({ scale: 0.4 }); 
      canvas.height = viewport.height;
      canvas.width = viewport.width;

      const renderContext = {
        canvasContext: context,
        viewport: viewport
      };
      
      // Dessiner dynamiquement la page du PDF dans la balise canvas HTML
      page.render(renderContext);
    }).catch(error => {
      console.error("Impossible de générer l'aperçu pour le PDF (" + url + ") :", error);
    });
  });
});