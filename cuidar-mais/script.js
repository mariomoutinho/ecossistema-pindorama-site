document.documentElement.classList.add("js-enabled");

document.addEventListener("DOMContentLoaded", function () {
  const menuToggle = document.querySelector(".menu-toggle");
  const menu = document.querySelector(".menu");
  const menuLinks = document.querySelectorAll(".menu a");

  const backToTop = document.getElementById("backToTop");
  const reveals = document.querySelectorAll(".reveal");

  const contactForm = document.getElementById("contactForm");
  const formFeedback = document.getElementById("formFeedback");

  // MENU MOBILE
  if (menuToggle && menu) {
    menuToggle.addEventListener("click", function () {
      const isOpen = menu.classList.toggle("open");
      menuToggle.setAttribute("aria-expanded", String(isOpen));
    });

    menuLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        if (window.innerWidth <= 820) {
          menu.classList.remove("open");
          menuToggle.setAttribute("aria-expanded", "false");
        }
      });
    });

    window.addEventListener("resize", function () {
      if (window.innerWidth > 820) {
        menu.classList.remove("open");
        menuToggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  // REVEAL AO SCROLL
  function revealOnScroll() {
    const trigger = window.innerHeight * 0.88;

    reveals.forEach(function (item) {
      const top = item.getBoundingClientRect().top;

      if (top < trigger) {
        item.classList.add("visible");
      }
    });
  }

  revealOnScroll();
  window.addEventListener("scroll", revealOnScroll);

  // BOTÃO VOLTAR AO TOPO
  if (backToTop) {
    window.addEventListener("scroll", function () {
      if (window.scrollY > 500) {
        backToTop.classList.add("visible");
      } else {
        backToTop.classList.remove("visible");
      }
    });

    backToTop.addEventListener("click", function () {
      window.scrollTo({
        top: 0,
        behavior: "smooth",
      });
    });
  }

  // FORMULÁRIO -> WHATSAPP
  if (contactForm) {
    contactForm.addEventListener("submit", function (event) {
      event.preventDefault();

      const formData = new FormData(contactForm);

      const nome = (formData.get("nome") || "").toString().trim();
      const instituicao = (formData.get("instituicao") || "").toString().trim();
      const email = (formData.get("email") || "").toString().trim();
      const telefone = (formData.get("telefone") || "").toString().trim();
      const mensagem = (formData.get("mensagem") || "").toString().trim();

      let texto = "Olá! Vim pelo site do Cuidar+.\n\n";

      if (nome) texto += `Nome: ${nome}\n`;
      if (instituicao) texto += `Instituição: ${instituicao}\n`;
      if (email) texto += `E-mail: ${email}\n`;
      if (telefone) texto += `WhatsApp: ${telefone}\n`;
      if (mensagem) texto += `Mensagem: ${mensagem}\n`;

      texto += "\nGostaria de conversar com a equipe do Cuidar+.";

      // Confere se esse número está completo
      const numeroWhatsapp = "558195216450";

      const url = `https://wa.me/${numeroWhatsapp}?text=${encodeURIComponent(texto)}`;

      if (formFeedback) {
        formFeedback.textContent = "Abrindo conversa no WhatsApp...";
        formFeedback.style.color = "#2d7f5e";
      }

      window.open(url, "_blank");
    });
  }
});