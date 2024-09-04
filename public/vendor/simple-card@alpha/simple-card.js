var F = (n) => {
  throw TypeError(n);
};
var J = (n, e, s) => e.has(n) || F("Cannot " + s);
var _ = (n, e, s) => e.has(n) ? F("Cannot add the same private member more than once") : e instanceof WeakSet ? e.add(n) : e.set(n, s);
var $ = (n, e, s) => (J(n, e, "access private method"), s);
const I = {
  mask: /^.*$/,
  preprocessors: [],
  postprocessors: [],
  plugins: [],
  overwriteMode: "shift"
};
class Q {
  constructor() {
    this.now = null, this.past = [], this.future = [];
  }
  undo() {
    const e = this.past.pop();
    e && this.now && (this.future.push(this.now), this.updateElement(e, "historyUndo"));
  }
  redo() {
    const e = this.future.pop();
    e && this.now && (this.past.push(this.now), this.updateElement(e, "historyRedo"));
  }
  updateHistory(e) {
    if (!this.now) {
      this.now = e;
      return;
    }
    const s = this.now.value !== e.value, t = this.now.selection.some((i, r) => i !== e.selection[r]);
    !s && !t || (s && (this.past.push(this.now), this.future = []), this.now = e);
  }
  updateElement(e, s) {
    this.now = e, this.updateElementState(e, { inputType: s, data: null });
  }
}
function ee(n, ...e) {
  return e.every(({ value: s }) => s === n.value);
}
function te(n, ...e) {
  return e.every(({ value: s, selection: t }) => s === n.value && t[0] === n.selection[0] && t[1] === n.selection[1]);
}
function ne({ value: n, selection: e }, s, t) {
  const [i, r] = e, l = typeof t == "function" ? t({ value: n, selection: e }) : t;
  return {
    value: n,
    selection: l === "replace" ? [i, i + s.length] : [i, r]
  };
}
function T(n) {
  return typeof n == "string";
}
function j(n, e, s, t) {
  let i = "";
  for (let r = e.length; r < n.length; r++) {
    const l = n[r], a = (t == null ? void 0 : t.value[r]) === l;
    if (!T(l) || l === s && !a)
      return i;
    i += l;
  }
  return i;
}
function P(n, e) {
  return Array.isArray(e) ? n.length === e.length && Array.from(n).every((s, t) => {
    const i = e[t];
    return T(i) ? s === i : s.match(i);
  }) : e.test(n);
}
function se(n, e, s) {
  let t = null, i = null;
  const r = Array.from(n.value).reduce((a, o, c) => {
    const u = j(e, a, o, s), d = a + u, h = e[d.length];
    return T(h) ? d + h : o.match(h) ? (t === null && c >= n.selection[0] && (t = d.length), i === null && c >= n.selection[1] && (i = d.length), d + o) : d;
  }, ""), l = j(e, r, "", s);
  return {
    value: P(r + l, e) ? r + l : r,
    selection: [t ?? r.length, i ?? r.length]
  };
}
function ie({ value: n, selection: e }, s) {
  const [t, i] = e;
  let r = t, l = i;
  return { value: Array.from(n).reduce((o, c, u) => {
    const d = o + c;
    return t === u && (r = o.length), i === u && (l = o.length), d.match(s) ? d : o;
  }, ""), selection: [r, l] };
}
function D(n, e, s = null) {
  if (P(n.value, e))
    return n;
  const { value: t, selection: i } = Array.isArray(e) ? se(n, e, s) : ie(n, e);
  return {
    selection: i,
    value: Array.isArray(e) ? t.slice(0, e.length) : t
  };
}
function H(n, e) {
  if (!Array.isArray(e))
    return n;
  const [s, t] = n.selection, i = [], r = Array.from(n.value).reduce((l, a, o) => {
    const c = e[o];
    return o === s && i.push(l.length), o === t && i.push(l.length), T(c) && c === a ? l : l + a;
  }, "");
  return i.length < 2 && i.push(...new Array(2 - i.length).fill(r.length)), {
    value: r,
    selection: [i[0], i[1]]
  };
}
class L {
  constructor(e, s) {
    this.initialElementState = e, this.maskOptions = s, this.value = "", this.selection = [0, 0];
    const { value: t, selection: i } = D(this.initialElementState, this.getMaskExpression(this.initialElementState));
    this.value = t, this.selection = i;
  }
  addCharacters([e, s], t) {
    const { value: i } = this, r = this.getMaskExpression({
      value: i.slice(0, e) + t + i.slice(s),
      selection: [e + t.length, e + t.length]
    }), l = { value: i, selection: [e, s] }, a = H(l, r), [o, c] = ne(a, t, this.maskOptions.overwriteMode).selection, u = a.value.slice(0, o) + t, d = u.length, h = D({
      value: u + a.value.slice(c),
      selection: [d, d]
    }, r, l);
    if (// eslint-disable-next-line @typescript-eslint/prefer-string-starts-ends-with
    i.slice(0, o) === D({
      value: u,
      selection: [d, d]
    }, r, l).value || te(this, h))
      throw new Error("Invalid mask value");
    this.value = h.value, this.selection = h.selection;
  }
  deleteCharacters([e, s]) {
    if (e === s || !s)
      return;
    const { value: t } = this, i = this.getMaskExpression({
      value: t.slice(0, e) + t.slice(s),
      selection: [e, e]
    }), r = { value: t, selection: [e, s] }, l = H(r, i), [a, o] = l.selection, c = l.value.slice(0, a) + l.value.slice(o), u = D({ value: c, selection: [a, a] }, i, r);
    this.value = u.value, this.selection = u.selection;
  }
  getMaskExpression(e) {
    const { mask: s } = this.maskOptions;
    return typeof s == "function" ? s(e) : s;
  }
}
class re {
  constructor(e) {
    this.element = e, this.listeners = [];
  }
  listen(e, s, t) {
    const i = s;
    this.element.addEventListener(e, i, t), this.listeners.push(() => this.element.removeEventListener(e, i));
  }
  destroy() {
    this.listeners.forEach((e) => e());
  }
}
const g = {
  CTRL: 1,
  ALT: 2,
  SHIFT: 4,
  META: 8
}, y = {
  Y: 89,
  Z: 90
};
function b(n, e, s) {
  return n.ctrlKey === !!(e & g.CTRL) && n.altKey === !!(e & g.ALT) && n.shiftKey === !!(e & g.SHIFT) && n.metaKey === !!(e & g.META) && /**
   * We intentionally use legacy {@link KeyboardEvent#keyCode `keyCode`} property. It is more
   * "keyboard-layout"-independent than {@link KeyboardEvent#key `key`} or {@link KeyboardEvent#code `code`} properties.
   * @see {@link https://github.com/taiga-family/maskito/issues/315 `KeyboardEvent#code` issue}
   */
  n.keyCode === s;
}
function le(n) {
  return b(n, g.CTRL, y.Y) || // Windows
  b(n, g.CTRL | g.SHIFT, y.Z) || // Windows & Android
  b(n, g.META | g.SHIFT, y.Z);
}
function oe(n) {
  return b(n, g.CTRL, y.Z) || // Windows & Android
  b(n, g.META, y.Z);
}
function ae({ value: n, selection: e }, s) {
  const [t, i] = e;
  if (t !== i)
    return [t, i];
  const r = s ? n.slice(t).indexOf(`
`) + 1 || n.length : n.slice(0, i).lastIndexOf(`
`) + 1;
  return [s ? t : r, s ? r : i];
}
function ce({ value: n, selection: e }, s) {
  const [t, i] = e;
  return t !== i ? [t, i] : (s ? [t, i + 1] : [t - 1, i]).map((l) => Math.min(Math.max(l, 0), n.length));
}
const ue = /\s+$/g, de = /^\s+/g, V = /\s/;
function he({ value: n, selection: e }, s) {
  const [t, i] = e;
  if (t !== i)
    return [t, i];
  if (s) {
    const o = n.slice(t), [c] = o.match(de) || [
      ""
    ], u = o.trimStart().search(V);
    return [
      t,
      u !== -1 ? t + c.length + u : n.length
    ];
  }
  const r = n.slice(0, i), [l] = r.match(ue) || [""], a = r.trimEnd().split("").reverse().findIndex((o) => o.match(V));
  return [
    a !== -1 ? i - l.length - a : 0,
    i
  ];
}
function A(n = []) {
  return (e, ...s) => n.reduce((t, i) => Object.assign(Object.assign({}, t), i(t, ...s)), e);
}
function pe(n, e) {
  const s = Object.assign(Object.assign({}, I), e), t = A(s.preprocessors), i = A(s.postprocessors), r = typeof n == "string" ? { value: n, selection: [0, 0] } : n, { elementState: l } = t({ elementState: r, data: "" }, "validation"), a = new L(l, s), { value: o, selection: c } = i(a, r);
  return typeof n == "string" ? o : { value: o, selection: c };
}
class C extends Q {
  constructor(e, s) {
    super(), this.element = e, this.maskitoOptions = s, this.isTextArea = this.element.nodeName === "TEXTAREA", this.eventListener = new re(this.element), this.options = Object.assign(Object.assign({}, I), this.maskitoOptions), this.preprocessor = A(this.options.preprocessors), this.postprocessor = A(this.options.postprocessors), this.teardowns = this.options.plugins.map((t) => t(this.element, this.options)), this.updateHistory(this.elementState), this.eventListener.listen("keydown", (t) => {
      if (le(t))
        return t.preventDefault(), this.redo();
      if (oe(t))
        return t.preventDefault(), this.undo();
    }), this.eventListener.listen("beforeinput", (t) => {
      var i;
      const r = t.inputType.includes("Forward");
      switch (this.updateHistory(this.elementState), t.inputType) {
        case "historyUndo":
          return t.preventDefault(), this.undo();
        case "historyRedo":
          return t.preventDefault(), this.redo();
        case "deleteByCut":
        case "deleteContentBackward":
        case "deleteContentForward":
          return this.handleDelete({
            event: t,
            isForward: r,
            selection: ce(this.elementState, r)
          });
        case "deleteWordForward":
        case "deleteWordBackward":
          return this.handleDelete({
            event: t,
            isForward: r,
            selection: he(this.elementState, r),
            force: !0
          });
        case "deleteSoftLineBackward":
        case "deleteSoftLineForward":
        case "deleteHardLineBackward":
        case "deleteHardLineForward":
          return this.handleDelete({
            event: t,
            isForward: r,
            selection: ae(this.elementState, r),
            force: !0
          });
        case "insertCompositionText":
          return;
        case "insertReplacementText":
          return;
        case "insertLineBreak":
        case "insertParagraph":
          return this.handleEnter(t);
        case "insertFromPaste":
        case "insertText":
        case "insertFromDrop":
        default:
          return this.handleInsert(t, t.data || // `event.data` for `contentEditable` is always `null` for paste/drop events
          ((i = t.dataTransfer) === null || i === void 0 ? void 0 : i.getData("text/plain")) || "");
      }
    }), this.eventListener.listen("input", ({ inputType: t }) => {
      t !== "insertCompositionText" && (this.ensureValueFitsMask(), this.updateHistory(this.elementState));
    }), this.eventListener.listen("compositionend", () => {
      this.ensureValueFitsMask(), this.updateHistory(this.elementState);
    });
  }
  get elementState() {
    const { value: e, selectionStart: s, selectionEnd: t } = this.element;
    return {
      value: e,
      selection: [s || 0, t || 0]
    };
  }
  get maxLength() {
    const { maxLength: e } = this.element;
    return e === -1 ? 1 / 0 : e;
  }
  destroy() {
    this.eventListener.destroy(), this.teardowns.forEach((e) => e == null ? void 0 : e());
  }
  updateElementState({ value: e, selection: s }, t = {
    inputType: "insertText",
    data: null
  }) {
    const i = this.elementState.value;
    this.updateValue(e), this.updateSelectionRange(s), i !== e && this.dispatchInputEvent(t);
  }
  updateSelectionRange([e, s]) {
    var t;
    const { element: i } = this;
    i.matches(":focus") && (i.selectionStart !== e || i.selectionEnd !== s) && ((t = i.setSelectionRange) === null || t === void 0 || t.call(i, e, s));
  }
  updateValue(e) {
    this.element.value = e;
  }
  ensureValueFitsMask() {
    this.updateElementState(pe(this.elementState, this.options));
  }
  dispatchInputEvent(e = {
    inputType: "insertText",
    data: null
  }) {
    globalThis.InputEvent && this.element.dispatchEvent(new InputEvent("input", Object.assign(Object.assign({}, e), { bubbles: !0, cancelable: !1 })));
  }
  handleDelete({ event: e, selection: s, isForward: t, force: i = !1 }) {
    const r = {
      value: this.elementState.value,
      selection: s
    }, [l, a] = r.selection, { elementState: o } = this.preprocessor({
      elementState: r,
      data: ""
    }, t ? "deleteForward" : "deleteBackward"), c = new L(o, this.options), [u, d] = o.selection;
    c.deleteCharacters([u, d]);
    const h = this.postprocessor(c, r);
    if (!(r.value.slice(0, l) + r.value.slice(a) === h.value && !i && !this.element.isContentEditable)) {
      if (e.preventDefault(), ee(r, o, c, h))
        return this.updateSelectionRange(t ? [d, d] : [u, u]);
      this.updateElementState(h, {
        inputType: e.inputType,
        data: null
      }), this.updateHistory(h);
    }
  }
  handleInsert(e, s) {
    const t = this.elementState, { elementState: i, data: r = s } = this.preprocessor({
      data: s,
      elementState: t
    }, "insert"), l = new L(i, this.options);
    try {
      l.addCharacters(i.selection, r);
    } catch {
      return e.preventDefault();
    }
    const [a, o] = i.selection, c = t.value.slice(0, a) + s + t.value.slice(o), u = this.postprocessor(l, t);
    if (u.value.length > this.maxLength)
      return e.preventDefault();
    (c !== u.value || this.element.isContentEditable) && (e.preventDefault(), this.updateElementState(u, {
      data: s,
      inputType: e.inputType
    }), this.updateHistory(u));
  }
  handleEnter(e) {
    (this.isTextArea || this.element.isContentEditable) && this.handleInsert(e, `
`);
  }
}
function fe(n, e, s) {
  const t = Math.min(Number(s), Math.max(Number(e), Number(n)));
  return n instanceof Date ? new Date(t) : t;
}
function ge(n) {
  return n.replaceAll(/\W/g, "").length;
}
const B = (n) => {
  var e, s, t;
  return {
    day: ((e = n.match(/d/g)) === null || e === void 0 ? void 0 : e.length) || 0,
    month: ((s = n.match(/m/g)) === null || s === void 0 ? void 0 : s.length) || 0,
    year: ((t = n.match(/y/g)) === null || t === void 0 ? void 0 : t.length) || 0
  };
};
function me(n) {
  return {
    day: String(n.getDate()).padStart(2, "0"),
    month: String(n.getMonth() + 1).padStart(2, "0"),
    year: String(n.getFullYear()).padStart(4, "0"),
    hours: String(n.getHours()).padStart(2, "0"),
    minutes: String(n.getMinutes()).padStart(2, "0"),
    seconds: String(n.getSeconds()).padStart(2, "0"),
    milliseconds: String(n.getMilliseconds()).padStart(3, "0")
  };
}
function ve(n, e) {
  return n.length < e.length ? !1 : n.split(/\D/).every((s) => !s.match(/^0+$/));
}
function W(n, e, s) {
  const t = ge(e);
  return n.replace(s, "").match(new RegExp(`(\\D*\\d[^\\d\\s]*){1,${t}}`, "g")) || [];
}
function M(n, e) {
  const s = e.replaceAll(/[^dmy]/g, ""), t = n.replaceAll(/\D+/g, ""), i = {
    day: t.slice(s.indexOf("d"), s.lastIndexOf("d") + 1),
    month: t.slice(s.indexOf("m"), s.lastIndexOf("m") + 1),
    year: t.slice(s.indexOf("y"), s.lastIndexOf("y") + 1)
  };
  return Object.fromEntries(Object.entries(i).filter(([r, l]) => !!l).sort(([r], [l]) => e.toLowerCase().indexOf(r[0]) > e.toLowerCase().indexOf(l[0]) ? 1 : -1));
}
function Ee(n, e) {
  var s, t, i, r, l, a, o;
  const c = ((s = n.year) === null || s === void 0 ? void 0 : s.length) === 2 ? `20${n.year}` : n.year, u = new Date(Number(c ?? "0"), Number((t = n.month) !== null && t !== void 0 ? t : "1") - 1, Number((i = n.day) !== null && i !== void 0 ? i : "1"), Number((r = void 0) !== null && r !== void 0 ? r : "0"), Number((l = void 0) !== null && l !== void 0 ? l : "0"), Number((a = void 0) !== null && a !== void 0 ? a : "0"), Number((o = void 0) !== null && o !== void 0 ? o : "0"));
  return u.setFullYear(Number(c ?? "0")), u;
}
const U = ", ";
function w({ day: n, month: e, year: s, hours: t, minutes: i, seconds: r, milliseconds: l }, { dateMode: a, dateTimeSeparator: o = U, timeMode: c }) {
  var u;
  const d = ((u = a.match(/y/g)) === null || u === void 0 ? void 0 : u.length) === 2 ? s == null ? void 0 : s.slice(-2) : s;
  return (a + (c ? o + c : "")).replaceAll(/d+/g, n ?? "").replaceAll(/m+/g, e ?? "").replaceAll(/y+/g, d ?? "").replaceAll(/H+/g, t ?? "").replaceAll("MSS", l ?? "").replaceAll(/M+/g, i ?? "").replaceAll(/S+/g, r ?? "").replaceAll(/^\D+/g, "").replaceAll(/\D+$/g, "");
}
const G = {
  day: 31,
  month: 12,
  year: 9999
}, Se = /* @__PURE__ */ new Date("0001-01-01"), ye = /* @__PURE__ */ new Date("9999-12-31"), be = [":", "."];
function we({ dateString: n, dateModeTemplate: e, dateSegmentsSeparator: s, offset: t, selection: [i, r] }) {
  const l = M(n, e), a = Object.entries(l), o = {};
  for (const [d, h] of a) {
    const f = w(o, {
      dateMode: e
    }), S = G[d], v = f.length && s.length, m = t + f.length + v + B(e)[d], E = m >= i && m === r;
    if (E && Number(h) > Number(S))
      return { validatedDateString: "", updatedSelection: [i, r] };
    if (E && Number(h) < 1)
      return { validatedDateString: "", updatedSelection: [i, r] };
    o[d] = h;
  }
  const c = w(o, {
    dateMode: e
  }), u = c.length - n.length;
  return {
    validatedDateString: c,
    updatedSelection: [
      i + u,
      r + u
    ]
  };
}
const Z = /[\\^$.*+?()[\]{}|]/g, De = new RegExp(Z.source);
function q(n) {
  return n && De.test(n) ? n.replaceAll(Z, "\\$&") : n;
}
function R(n, e, s = 0) {
  return Number(n.padEnd(e.length, "0")) <= Number(e) ? { validatedSegmentValue: n, prefixedZeroesCount: s } : n.endsWith("0") ? R(`0${n.slice(0, e.length - 1)}`, e, s + 1) : R(`${n.slice(0, e.length - 1)}0`, e, s);
}
function N(n) {
  return n.replaceAll(/[０-９]/g, (e) => String.fromCharCode(e.charCodeAt(0) - 65248));
}
function Ae({ dateModeTemplate: n, dateSegmentSeparator: e, splitFn: s, uniteFn: t }) {
  return ({ value: i, selection: r }) => {
    var l;
    const [a, o] = r, { dateStrings: c, restPart: u = "" } = s(i), d = [];
    let h = 0;
    c.forEach((S) => {
      const v = M(S, n), E = Object.entries(v).reduce((k, [O, K]) => {
        const { validatedSegmentValue: Y, prefixedZeroesCount: X } = R(K, `${G[O]}`);
        return h += X, Object.assign(Object.assign({}, k), { [O]: Y });
      }, {});
      d.push(w(E, { dateMode: n }));
    });
    const f = t(d, i) + (!((l = c[c.length - 1]) === null || l === void 0) && l.endsWith(e) ? e : "") + u;
    return h && f.slice(o + h, o + h + e.length) === e && (h += e.length), {
      selection: [a + h, o + h],
      value: f
    };
  };
}
function xe() {
  return ({ elementState: n, data: e }) => {
    const { value: s, selection: t } = n;
    return {
      elementState: {
        selection: t,
        value: N(s)
      },
      data: N(e)
    };
  };
}
function Te(n, e) {
  const s = B(e);
  return Object.fromEntries(Object.entries(n).map(([t, i]) => {
    const r = s[t];
    return [
      t,
      i.length === r && i.match(/^0+$/) ? "1".padStart(r, "0") : i
    ];
  }));
}
function ke({ dateModeTemplate: n, min: e = Se, max: s = ye, rangeSeparator: t = "", dateSegmentSeparator: i = "." }) {
  return ({ value: r, selection: l }) => {
    const a = t && r.endsWith(t), o = W(r, n, t);
    let c = "";
    for (const u of o) {
      c += c ? t : "";
      const d = M(u, n);
      if (!ve(u, n)) {
        const S = Te(d, n), v = w(S, { dateMode: n }), m = u.endsWith(i) ? i : "";
        c += v + m;
        continue;
      }
      const h = Ee(d), f = fe(h, e, s);
      c += w(me(f), {
        dateMode: n
      });
    }
    return {
      selection: l,
      value: c + (a ? t : "")
    };
  };
}
function Ce({ dateModeTemplate: n, dateSegmentsSeparator: e, rangeSeparator: s = "", dateTimeSeparator: t = U }) {
  return ({ elementState: i, data: r }) => {
    const l = s ? new RegExp(`${s}|-`) : t, a = r.split(l), o = r.includes(t) ? [a[0]] : a;
    if (o.every((c) => c.trim().split(/\D/).filter(Boolean).length === n.split(e).length)) {
      const c = o.map((u) => Le(u, n, e)).join(s);
      return {
        elementState: i,
        data: `${c}${r.includes(t) && t + a[1] || ""}`
      };
    }
    return { elementState: i, data: r };
  };
}
function Le(n, e, s) {
  const t = n.split(/\D/).filter(Boolean), i = e.split(s);
  return t.map((l, a) => a === i.length - 1 ? l : l.padStart(i[a].length, "0")).join(s);
}
function Re({ dateModeTemplate: n, dateSegmentsSeparator: e, rangeSeparator: s = "" }) {
  return ({ elementState: t, data: i }) => {
    const { value: r, selection: l } = t;
    if (i === e)
      return {
        elementState: t,
        data: l[0] === r.length ? i : ""
      };
    const a = i.replaceAll(new RegExp(`[^\\d${q(e)}${s}]`, "g"), "");
    if (!a)
      return { elementState: t, data: "" };
    const [o, c] = l;
    let u = c + i.length;
    const d = r.slice(0, o) + a + r.slice(u), h = W(d, n, s);
    let f = "";
    const S = !!s && d.includes(s);
    for (const m of h) {
      const { validatedDateString: E, updatedSelection: k } = we({
        dateString: m,
        dateModeTemplate: n,
        dateSegmentsSeparator: e,
        offset: f.length,
        selection: [o, u]
      });
      if (m && !E)
        return { elementState: t, data: "" };
      u = k[1], f += S && !f ? E + s : E;
    }
    const v = f.slice(o, u);
    return {
      elementState: {
        selection: l,
        value: f.slice(0, o) + v.split(e).map((m) => "0".repeat(m.length)).join(e) + f.slice(u)
      },
      data: v
    };
  };
}
function Ie() {
  return ({ elementState: n }, e) => {
    const { value: s, selection: t } = n;
    if (!s || Me(s, t))
      return { elementState: n };
    const [i, r] = t, l = s.slice(i, r).replaceAll(/\d/g, "0"), a = s.slice(0, i) + l + s.slice(r);
    return e === "validation" || e === "insert" && i === r ? {
      elementState: { selection: t, value: a }
    } : {
      elementState: {
        selection: e === "deleteBackward" || e === "insert" ? [i, i] : [r, r],
        value: a
      }
    };
  };
}
function Me(n, [e, s]) {
  return s === n.length;
}
function Oe({ mode: n, separator: e = ".", max: s, min: t }) {
  const i = n.split("/").join(e);
  return Object.assign(Object.assign({}, I), { mask: Array.from(i).map((r) => e.includes(r) ? r : /\d/), overwriteMode: "replace", preprocessors: [
    xe(),
    Ie(),
    Ce({
      dateModeTemplate: i,
      dateSegmentsSeparator: e
    }),
    Re({
      dateModeTemplate: i,
      dateSegmentsSeparator: e
    })
  ], postprocessors: [
    Ae({
      dateModeTemplate: i,
      dateSegmentSeparator: e,
      splitFn: (r) => ({ dateStrings: [r] }),
      uniteFn: ([r]) => r
    }),
    ke({
      min: t,
      max: s,
      dateModeTemplate: i,
      dateSegmentSeparator: e
    })
  ] });
}
new RegExp(`[${be.map(q).join("")}]$`);
const Fe = /^(?:\d{4}[ -]?){0,3}\d{0,4}$/, p = {
  // Visa: Starts with 4, 13 or 16 digits
  visa: {
    final: /^4(?:\d{3}[- ]?){3}\d{3,4}$/,
    // Exactly 13 or 16 digits
    start: /^4/,
    // Checks if the input starts with 4
    length: /^4\d{0,15}$/
    // Strictly matches 1 to 16 digits after the initial 4, no spaces or dashes
  },
  // MasterCard: Starts with 51-55, 16 digits
  mastercard: {
    final: /^5[1-5]\d{3}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}$/,
    // Exactly 16 digits
    start: /^5[1-5]/,
    // Checks if the input starts with 51-55
    length: /^5[1-5]\d{0,15}$/
    // Strictly matches 2 to 16 digits after the initial 51-55, no spaces or dashes
  },
  // American Express: Starts with 34 or 37, 15 digits
  amex: {
    final: /^3[47]\d{2}[- ]?\d{6}[- ]?\d{5}$/,
    // Exactly 15 digits
    start: /^3[47]/,
    // Checks if the input starts with 34 or 37
    length: /^3[47]\d{0,15}$/
    // Strictly matches 2 to 15 digits after the initial 34 or 37, no spaces or dashes
  },
  // Discover: Starts with 6011 or 65 or 64[4-9], 16 digits
  discover: {
    final: /^(6011|65|64[4-9])\d{4}[- ]?\d{4}[- ]?\d{4}$/,
    // Exactly 16 digits
    start: /^(6011|65|64[4-9])/,
    // Checks if the input starts with 6011, 65, or 64 followed by 4-9
    length: /^(6011|65|64[4-9])\d{0,15}$/
    // Strictly matches 4 to 16 digits after the initial prefix, no spaces or dashes
  },
  // Diners Club: Starts with 30[0-5], 36, 38, or 39, 14 digits
  diners: {
    final: /^(30[0-5]|36|38|39)\d{4}[- ]?\d{4}[- ]?\d{4}$/,
    // Exactly 14 digits
    start: /^(30[0-5]|36|38|39)/,
    // Checks if the input starts with 30-35, 36, 38, or 39
    length: /^(30[0-5]|36|38|39)\d{0,14}$/
    // Strictly matches 2 to 14 digits after the initial prefix, no spaces or dashes
  },
  // JCB: Starts with 2131, 1800, or 35[0-9]{3}, 15 or 16 digits
  jcb: {
    final: /^(2131|1800|35[0-9]{3})\d{4}[- ]?\d{4}[- ]?\d{4}$/,
    // Exactly 15 or 16 digits
    start: /^(2131|1800|35[0-9]{3})/,
    // Checks if the input starts with 2131, 1800, or 35 followed by 3 digits
    length: /^(2131|1800|35[0-9]{3})\d{0,15}$/
    // Strictly matches 4 to 16 digits after the initial prefix, no spaces or dashes
  }
};
var x, z;
class $e {
  constructor(e) {
    _(this, x);
    this.options = e;
  }
  mount() {
    return this.number = this.options.fields.card.number instanceof HTMLInputElement ? this.options.fields.card.number : document.querySelector(
      this.options.fields.card.number
    ), this.date = this.options.fields.card.date instanceof HTMLInputElement ? this.options.fields.card.date : document.querySelector(
      this.options.fields.card.date
    ), this.cvv = this.options.fields.card.cvv instanceof HTMLInputElement ? this.options.fields.card.cvv : document.querySelector(
      this.options.fields.card.cvv
    ), $(this, x, z).call(this), this;
  }
  check() {
    const e = p.visa.final.test(this.number.value) || p.mastercard.final.test(this.number.value) || p.amex.final.test(this.number.value) || p.discover.final.test(this.number.value) || p.diners.final.test(this.number.value) || p.jcb.final.test(this.number.value), s = new RegExp("^(0[1-9]|1[0-2])/(?:\\d{2})$").test(
      this.date.value
    ), t = new RegExp("^\\d{3}$").test(this.cvv.value);
    return {
      valid: e && s && t,
      number: {
        valid: e,
        value: this.number.value
      },
      date: {
        valid: s,
        value: this.date.value
      },
      cvv: {
        valid: t,
        value: this.cvv.value
      }
    };
  }
  type() {
    return p.visa.start.test(this.number.value) ? "visa" : p.mastercard.start.test(this.number.value) ? "mastercard" : p.amex.start.test(this.number.value) ? "amex" : p.discover.start.test(this.number.value) ? "discover" : p.diners.start.test(this.number.value) ? "diners" : p.jcb.start.test(this.number.value) ? "jcb" : "unknown";
  }
}
x = new WeakSet(), z = function() {
  new C(this.number, {
    mask: (e) => p.visa.start.test(e.value) ? new RegExp(p.visa.length) : p.mastercard.start.test(e.value) ? new RegExp(p.mastercard.length) : p.amex.start.test(e.value) ? new RegExp(p.amex.length) : p.discover.start.test(e.value) ? new RegExp(p.discover.length) : p.diners.start.test(e.value) ? new RegExp(p.diners.length) : p.jcb.start.test(e.value) ? new RegExp(p.jcb.length) : new RegExp(Fe)
  }), new C(
    this.date,
    Oe({
      mode: "mm/yy",
      separator: "/"
    })
  ), new C(this.cvv, {
    mask: [/\d/, /\d/, /\d/]
  });
};
export {
  $e as SimpleCard,
  p as masks,
  Fe as numbers
};
