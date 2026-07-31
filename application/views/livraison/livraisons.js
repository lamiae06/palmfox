const activeLink = document.querySelector('.sidebar-nav a[href*="livraisons.php"]');
if (activeLink) {
    activeLink.classList.add('active');
}
function toggleFilterOptions() {

  const panel = document.getElementById("filterPanel");
  if (panel) {
    panel.classList.toggle("active");
  }
}

function openModal(mode, id = "", commande = "", status = "En cours") {
  const modal = document.getElementById("deliveryModal");
  const title = document.getElementById("modalTitle");
  const submitBtn = document.getElementById("formSubmitBtn");

  if (!modal) return;

  if (mode === "edit") {
    title.innerText = "Modifier la Livraison";
    if (submitBtn) submitBtn.innerText = "Sauvegarder les modifications";
    
    document.getElementById("deliveryId").value = id;
    document.getElementById("formCommande").value = commande;
    document.getElementById("formStatus").value = status;
  } else {
    title.innerText = "Nouvelle Livraison";
    if (submitBtn) submitBtn.innerText = "Valider";
    
    document.getElementById("deliveryId").value = "";
    const selectCmd = document.getElementById("formCommande");
    const selectStat = document.getElementById("formStatus");
    if (selectCmd) selectCmd.selectedIndex = 0;
    if (selectStat) selectStat.selectedIndex = 0;
  }
  modal.classList.add("active");
}

function closeModal() {
  const modal = document.getElementById("deliveryModal");
  if (modal) modal.classList.remove("active");
}

function openDeleteModal(deliveryCode) {
  const nameElem = document.getElementById("deleteDeliveryName");
  const idInput = document.getElementById("id_livraison");
  const modal = document.getElementById("deleteModal");

  if (nameElem) nameElem.innerText = deliveryCode;
  if (idInput) idInput.value = deliveryCode;
  if (modal) modal.classList.add("active");
}

function closeDeleteModal() {
  const modal = document.getElementById("deleteModal");
  if (modal) modal.classList.remove("active");
}


//   LOGIQUE ACCÉLÉRÉE : RECHERCHE ET FILTRE LIVE
document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.querySelector(".search-box input");
  const filterStatus = document.getElementById("filterStatus");
  const btnApplyFiltre = document.querySelector("#filterPanel button"); // Le bouton appliquer
  const tableRows = document.querySelectorAll(".data-table tbody tr");


  if (tableRows.length === 0 || (tableRows.length === 1 && tableRows[0].cells.length === 1)) return;

  function runDeliveryFilter() {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const statusValue = filterStatus ? filterStatus.value : "all";

    tableRows.forEach(row => {
      const deliveryId = row.cells[0] ? row.cells[0].innerText.toLowerCase() : "";
      const commandeId = row.cells[1] ? row.cells[1].innerText.toLowerCase() : "";
      
      
      const badgeText = row.cells[2] ? row.cells[2].innerText.trim() : "";


      const matchesSearch = deliveryId.includes(query) || commandeId.includes(query);


      let matchesStatus = false;
      if (statusValue === "all") {
        matchesStatus = true;
      } else if (statusValue === "pending" && badgeText === "En cours") {
        matchesStatus = true;
      } else if (statusValue === "done" && badgeText === "Livrée / Facturée") {
        matchesStatus = true;
      }

     
      if (matchesSearch && matchesStatus) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  }

  if (searchInput) {
    searchInput.addEventListener("input", runDeliveryFilter);
  }

  if (btnApplyFiltre) {
    btnApplyFiltre.addEventListener("click", (e) => {
      e.preventDefault();
      runDeliveryFilter();
      toggleFilterOptions();
    });
  }
});