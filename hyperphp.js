/**
 * HyperPHP runtime client — v0.1
 *
 * Ce fichier est le SEUL JavaScript que le développeur n'écrit jamais.
 * Rôle : intercepter les interactions déclarées via data-action, envoyer
 * l'état signé + l'action au serveur, puis morpher le DOM avec la réponse
 * plutôt que de remplacer le innerHTML en bloc (ce qui casserait le focus,
 * le scroll, et les animations en cours).
 *
 * Taille cible : < 5 Ko minifié. Zéro dépendance externe.
 */
(function () {
  'use strict';

  const ENDPOINT = document.currentScript?.dataset.endpoint || '/hyperphp/action';

  /** Trouve le conteneur de composant le plus proche d'un élément. */
  function closestComponent(el) {
    return el.closest('[data-component]');
  }

  /** Envoie une action au serveur et renvoie {html} ou {error}. */
  async function sendAction(component, action, payload) {
    const token = component.getAttribute('data-state');

    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ state: token, action, payload: payload || [] }),
    });

    return response.json();
  }

  /**
   * Morphing DOM différentiel minimal (v0.1).
   *
   * Contrairement à un patch "innerHTML = nouveauHTML", on compare noeud par
   * noeud l'arbre existant et le nouvel arbre, et on ne touche que ce qui a
   * changé : texte, attributs, ou structure. Ça préserve le focus d'un champ
   * de saisie, la position de scroll, et les éléments non affectés par le
   * changement d'état.
   *
   * Limite connue v0.1 : les clés de réconciliation de listes (comme un
   * "key" React) ne sont pas encore gérées — chaque enfant est comparé par
   * position, pas par identité. C'est documenté en roadmap v0.2 (nécessaire
   * avant d'utiliser HyperPHP sur des listes réordonnables).
   */
  function morph(oldNode, newNode) {
    // Cas 1 : type de noeud différent (ex: DIV vs SPAN) -> remplacement net,
    // pas de tentative de patch partiel qui n'aurait pas de sens.
    if (oldNode.nodeType !== newNode.nodeType || oldNode.nodeName !== newNode.nodeName) {
      oldNode.replaceWith(newNode.cloneNode(true));
      return;
    }

    // Cas 2 : noeud texte -> ne réécrire que si le contenu a changé.
    if (oldNode.nodeType === Node.TEXT_NODE) {
      if (oldNode.textContent !== newNode.textContent) {
        oldNode.textContent = newNode.textContent;
      }
      return;
    }

    if (oldNode.nodeType !== Node.ELEMENT_NODE) {
      return;
    }

    // Cas 3 : élément -> synchronise les attributs (sans toucher au focus,
    // à la valeur en cours de saisie côté client si l'utilisateur tape,
    // ni au scroll de l'élément).
    syncAttributes(oldNode, newNode);

    // Ne jamais écraser la valeur d'un champ que l'utilisateur est en train
    // de modifier activement (élément ayant le focus).
    const isActiveInput =
      (oldNode.tagName === 'INPUT' || oldNode.tagName === 'TEXTAREA') &&
      document.activeElement === oldNode;

    if (!isActiveInput && 'value' in oldNode && 'value' in newNode) {
      if (oldNode.value !== newNode.value) {
        oldNode.value = newNode.value;
      }
    }

    morphChildren(oldNode, newNode);
  }

  function syncAttributes(oldEl, newEl) {
    const oldAttrs = oldEl.attributes;
    const newAttrs = newEl.attributes;

    // Supprime les attributs qui n'existent plus.
    for (let i = oldAttrs.length - 1; i >= 0; i--) {
      const name = oldAttrs[i].name;
      if (!newEl.hasAttribute(name)) {
        oldEl.removeAttribute(name);
      }
    }

    // Ajoute/actualise les attributs modifiés.
    for (let i = 0; i < newAttrs.length; i++) {
      const { name, value } = newAttrs[i];
      if (oldEl.getAttribute(name) !== value) {
        oldEl.setAttribute(name, value);
      }
    }
  }

  function morphChildren(oldParent, newParent) {
    const oldChildren = Array.from(oldParent.childNodes);
    const newChildren = Array.from(newParent.childNodes);
    const max = Math.max(oldChildren.length, newChildren.length);

    for (let i = 0; i < max; i++) {
      const oldChild = oldChildren[i];
      const newChild = newChildren[i];

      if (oldChild && newChild) {
        morph(oldChild, newChild);
      } else if (!oldChild && newChild) {
        oldParent.appendChild(newChild.cloneNode(true));
      } else if (oldChild && !newChild) {
        oldParent.removeChild(oldChild);
      }
    }
  }

  /** Applique un fragment HTML reçu du serveur sur un composant existant. */
  function applyPatch(component, html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    const newRoot = template.content.firstElementChild;

    if (!newRoot) {
      return;
    }

    morph(component, newRoot);
  }

  async function onAction(event) {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) {
      return;
    }

    // data-on="click" (défaut) permet de limiter une action à un évènement
    // précis ; sans attribut, on réagit au click par défaut.
    const eventName = trigger.getAttribute('data-on') || 'click';
    if (event.type !== eventName) {
      return;
    }

    const component = closestComponent(trigger);
    if (!component) {
      return;
    }

    const action = trigger.getAttribute('data-action');
    let payload = [];
    const rawPayload = trigger.getAttribute('data-payload');
    if (rawPayload) {
      try {
        payload = JSON.parse(rawPayload);
      } catch {
        payload = [];
      }
    }

    trigger.setAttribute('aria-busy', 'true');

    try {
      const result = await sendAction(component, action, payload);

      if (result.error) {
        console.error('[HyperPHP] action refusée par le serveur:', result.error);
        return;
      }

      applyPatch(component, result.html);
    } catch (err) {
      console.error('[HyperPHP] échec réseau lors de l\'action:', err);
    } finally {
      trigger.removeAttribute('aria-busy');
    }
  }

  // Délégation d'évènements sur le document entier : fonctionne aussi pour
  // les éléments injectés dynamiquement par un patch précédent, sans avoir
  // à ré-attacher des listeners après chaque morph.
  ['click', 'input', 'change', 'submit'].forEach((eventName) => {
    document.addEventListener(eventName, onAction, true);
  });

  window.HyperPHP = { morph, applyPatch };
})();
