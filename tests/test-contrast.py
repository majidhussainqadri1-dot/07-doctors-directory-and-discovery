from math import pow
pairs=[('#ffffff','#0f6b3f','primary button'),('#ffffff','#084a2c','primary hover'),('#152033','#ffffff','body text'),('#586273','#ffffff','muted text'),('#084a2c','#eaf7f0','green badge'),('#6e4300','#fff3d7','featured badge'),('#234b37','#eaf7f0','disclaimer')]
def rgb(h): h=h.lstrip('#'); return tuple(int(h[i:i+2],16)/255 for i in (0,2,4))
def lum(h):
 def c(v): return v/12.92 if v<=.04045 else pow((v+.055)/1.055,2.4)
 r,g,b=rgb(h); return .2126*c(r)+.7152*c(g)+.0722*c(b)
def ratio(a,b):
 x,y=sorted((lum(a),lum(b)),reverse=True); return (x+.05)/(y+.05)
failed=[]
for fg,bg,name in pairs:
 r=ratio(fg,bg); ok=r>=4.5
 print(('PASS' if ok else 'FAIL')+f': {name} {r:.2f}:1')
 if not ok: failed.append(name)
if failed: raise SystemExit('Contrast failed: '+', '.join(failed))
print(f'TOTAL PASS: {len(pairs)}')
