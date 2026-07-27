# Dark mode

Everyone picks their own, from the sun/moon button in the top bar: **Light**, **Dark** or
**System**. New accounts start on System.

Three choices rather than a switch, because System is the one most people want and a two-way toggle
cannot express it. Choosing it means following the device as it changes — a machine that goes dark
at sunset takes the panel with it, without a reload.

The choice is stored on the account (`users.theme`), so it follows the person to any machine they
sign in from. That also means the server knows it while rendering: the right theme is set on the
first byte, and the page never flashes light and swaps. Only `System` needs the browser, and that
is resolved before the first paint.

## How it works, and why that way

The admin deploy runs **no npm build**, so anything needing Tailwind to regenerate — `dark:`
variants, for instance — is not available.

What makes this possible instead: the compiled stylesheet is Tailwind v4, where every utility
points at a CSS variable.

```css
.bg-white        { background-color: var(--color-white) }
.border-gray-200 { border-color:     var(--color-gray-200) }
.text-gray-400   { color:            var(--color-gray-400) }
```

So `public/css/theme-dark.css` redefines those variables under `:root[data-theme='dark']`, and a
single file flips roughly four thousand utility usages across the panel with no markup changes at
all.

## The two variables that could not simply be flipped

- **`--color-white`** is both a surface (`bg-white`, 753 uses) and a label on coloured buttons
  (`text-white`, 329 uses). Flipping it would have put dark text on a dark blue button, so it is
  left alone and `.bg-white` is overridden directly instead.
- **`--color-gray-*`** are both surfaces (`bg-gray-50`) and text (`text-gray-400`). A mechanical
  inversion leaves mid-greys at the lightness they started, which on a dark background is
  unreadable — so each step is picked for the job it actually does in this codebase rather than
  mirrored.

A handful of other things are handled explicitly: checkboxes and radios are drawn by hand because
the native control paints itself pale whatever the background says; shadows become hairlines,
since a shadow is invisible on dark; and photographs are dimmed slightly, being authored for light
pages.

## Adding to it

Use the existing neutral utilities (`bg-white`, `border-gray-200`, `text-gray-400`) and new screens
theme themselves. Reach for a hard-coded hex or an inline `background:#fff` and it will stay light
in both — that is the one thing to avoid.

## What was checked

The dashboard, a form-heavy settings screen and a dense table were verified in both themes. The
panel has far more screens than that; the variable approach means they are all covered by the same
override, but a screen that hard-codes a colour will show it.
