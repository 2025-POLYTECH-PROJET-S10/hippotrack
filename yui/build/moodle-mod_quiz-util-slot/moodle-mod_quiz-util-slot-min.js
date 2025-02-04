YUI.add("moodle-mod_hippotrack-util-slot", function (u, e) { u.namespace("Moodle.mod_hippotrack.util.slot"), u.Moodle.mod_hippotrack.util.slot = { CSS: { SLOT: "slot", QUESTIONTYPEDESCRIPTION: "qtype_description", CANNOT_DEPEND: "question_dependency_cannot_depend" }, CONSTANTS: { SLOTIDPREFIX: "slot-", QUESTION: M.util.get_string("question", "moodle") }, SELECTORS: { SLOT: "li.slot", INSTANCENAME: ".instancename", NUMBER: "span.slotnumber", PAGECONTENT: "div#page-content", PAGEBREAK: "span.page_split_join_wrapper", ICON: ".icon", QUESTIONTYPEDESCRIPTION: ".qtype_description", SECTIONUL: "ul.section", DEPENDENCY_WRAPPER: ".question_dependency_wrapper", DEPENDENCY_LINK: ".question_dependency_wrapper .cm-edit-action", DEPENDENCY_ICON: ".question_dependency_wrapper .icon" }, getSlotFromComponent: function (e) { return u.one(e).ancestor(this.SELECTORS.SLOT, !0) }, getId: function (e) { e = e.get("id").replace(this.CONSTANTS.SLOTIDPREFIX, ""); return !("number" != typeof (e = parseInt(e, 10)) || !isFinite(e)) && e }, getName: function (e) { e = e.one(this.SELECTORS.INSTANCENAME); return e ? e.get("firstChild").get("data") : null }, getNumber: function (e) { if (!e) return !1; e = e.one(this.SELECTORS.NUMBER).get("text").replace(this.CONSTANTS.QUESTION, ""); return !("number" != typeof (e = parseInt(e, 10)) || !isFinite(e)) && e }, setNumber: function (e, t) { e.one(this.SELECTORS.NUMBER).setHTML('<span class="accesshide">' + this.CONSTANTS.QUESTION + "</span> " + t) }, getSlots: function () { return u.all(this.SELECTORS.PAGECONTENT + " " + this.SELECTORS.SECTIONUL + " " + this.SELECTORS.SLOT) }, getNumberedSlots: function () { var e = this.SELECTORS.PAGECONTENT + " " + this.SELECTORS.SECTIONUL; return e += " " + this.SELECTORS.SLOT + ":not(" + this.SELECTORS.QUESTIONTYPEDESCRIPTION + ")", u.all(e) }, getPrevious: function (e) { return e.previous(this.SELECTORS.SLOT) }, getPreviousNumbered: function (e) { var t, i, n = e.previous(this.SELECTORS.SLOT + ":not(" + this.SELECTORS.QUESTIONTYPEDESCRIPTION + ")"); if (n) return n; for (t = e.ancestor("li.section").previous("li.section"); t;) { if (0 < (i = t.all(this.SELECTORS.SLOT + ":not(" + this.SELECTORS.QUESTIONTYPEDESCRIPTION + ")")).size()) return i.item(i.size() - 1); t = t.previous("li.section") } return !1 }, reorderSlots: function () { this.getSlots().each(function (e) { var t, i; u.Moodle.mod_hippotrack.util.page.getPageFromSlot(e) || (t = e.next(u.Moodle.mod_hippotrack.util.page.SELECTORS.PAGE), e.swap(t)), t = this.getPreviousNumbered(e), i = 0, e.hasClass(this.CSS.QUESTIONTYPEDESCRIPTION) || (t && (i = this.getNumber(t)), this.setNumber(e, i + 1)) }, this) }, updateOneSlotSections: function () { u.all(".mod-quiz-edit-content ul.slots li.section").each(function (e) { 1 < e.all(this.SELECTORS.SLOT).size() ? e.removeClass("only-has-one-slot") : e.addClass("only-has-one-slot") }, this) }, remove: function (e) { var t = u.Moodle.mod_hippotrack.util.page.getPageFromSlot(e); e.remove(), u.Moodle.mod_hippotrack.util.page.isEmpty(t) && u.Moodle.mod_hippotrack.util.page.remove(t) }, getPageBreaks: function () { var e = this.SELECTORS.PAGECONTENT + " " + this.SELECTORS.SECTIONUL; return e += " " + this.SELECTORS.SLOT + this.SELECTORS.PAGEBREAK, u.all(e) }, getPageBreak: function (e) { return u.one(e).one(this.SELECTORS.PAGEBREAK) }, addPageBreak: function (e) { var t = M.mod_hippotrack.resource_toolbox.get("config").addpageiconhtml, t = t.replace("%%SLOT%%", this.getNumber(e)), t = u.Node.create(t); return e.one("div").insert(t, "after"), t }, removePageBreak: function (e) { e = this.getPageBreak(e); return !!e && (e.remove(), !0) }, reorderPageBreaks: function () { var E = this.getSlots(), a = 0; E.each(function (e, t) { var i, n, o, s, r; if (a++, i = this.getPageBreak(e), n = e.next("li.activity")) { for (r in (i = i || this.addPageBreak(e)) && t === E.size() - 1 && this.removePageBreak(e), t = i.get("childNodes").item(0), i = e = "", i = u.Moodle.mod_hippotrack.util.page.isPage(n) ? (e = "removepagebreak", "e/remove_page_break") : (e = "addpagebreak", "e/insert_page_break"), t.set("title", M.util.get_string(e, "quiz")), t.setData("action", e), (n = t.one(this.SELECTORS.ICON)).set("title", M.util.get_string(e, "quiz")), n.set("alt", M.util.get_string(e, "quiz")), n.set("src", M.util.image_url(i)), (o = u.QueryString.parse(t.get("href"))).slot = a, s = "", o) s.length && (s += "&"), s += r + "=" + o[r]; t.set("href", s) } }, this) }, updateAllDependencyIcons: function () { var e = this.getSlots(), t = 0, i = null; e.each(function (e) { 1 == ++t || "0" === i.getData("canfinish") ? e.one(this.SELECTORS.DEPENDENCY_WRAPPER).addClass(this.CSS.CANNOT_DEPEND) : e.one(this.SELECTORS.DEPENDENCY_WRAPPER).removeClass(this.CSS.CANNOT_DEPEND), this.updateDependencyIcon(e, null), i = e }, this) }, updateDependencyIcon: function (e, t) { var i = e.one(this.SELECTORS.DEPENDENCY_LINK), n = e.one(this.SELECTORS.DEPENDENCY_ICON), o = this.getPrevious(e), e = { thisq: this.getNumber(e) }; o && (e.previousq = this.getNumber(o)), (t = null === t ? "removedependency" === i.getData("action") : t) ? (i.set("title", M.util.get_string("questiondependencyremove", "quiz", e)), i.setData("action", "removedependency"), window.require(["core/templates"], function (e) { e.renderPix("t/locked", "core", M.util.get_string("questiondependsonprevious", "hippotrack")).then(function (e) { n.replace(e) }) })) : (i.set("title", M.util.get_string("questiondependencyadd", "quiz", e)), i.setData("action", "adddependency"), window.require(["core/templates"], function (e) { e.renderPix("t/unlocked", "core", M.util.get_string("questiondependencyfree", "hippotrack")).then(function (e) { n.replace(e) }) })) } } }, "@VERSION@", { requires: ["node", "moodle-mod_hippotrack-util-base"] });