import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../public/assets/app.js',import.meta.url),'utf8');
const prefix=source.slice(0,source.indexOf('const fmt=d=>'));
const render=input=>vm.runInNewContext(prefix+'\nformatAiText(input)',{input});
assert.equal(render('### **Formation**\n\n**Action** : exercer la relecture.\n- Support\n- Résultats'),'<h3>Formation</h3><p><strong>Action</strong> : exercer la relecture.</p><ul><li>Support</li><li>Résultats</li></ul>');
assert.equal(render('1. Première mesure\n2. Deuxième mesure'),'<ol start="1"><li>Première mesure</li><li>Deuxième mesure</li></ol>');
assert.equal(render('Une erreur vaut 3 * 4.'),'<p>Une erreur vaut 3 * 4.</p>');
for(const attack of ['<img src=x onerror=alert(1)>','**<svg onload=alert(1)>**','### <script>alert(1)</script>','[ouvrir](javascript:alert(1))','&#x3c;iframe src=x&#x3e;','\" onmouseover=alert(1)']){
 const html=render(attack);
 assert.ok(!/<(?:script|svg|img|iframe|a|input)\b/i.test(html));
 for(const tag of html.matchAll(/<([^>]+)>/g))assert.match(tag[1],/^(?:\/?(?:p|strong|em|code|h3|ul|li|ol)|ol start="\d{1,4}")$/);
}
console.log('OK : titres, emphase, listes, texte littéral et six scénarios d’injection restent inertes.');

const plan=JSON.stringify({slides:Array.from({length:6},()=>({title:'<img src=x onerror=alert(1)>',bullets:['<svg onload=alert(1)>'],notes:'<script>alert(1)</script>'}))});
const draft=vm.runInNewContext(prefix+'\nformatAiDraft(input)',{input:plan});
assert.ok(!/<(?:script|svg|img|iframe|a|input)\b/i.test(draft));
assert.ok(draft.includes('&lt;img'));
console.log('OK : plan structuré et notes échappés.');
