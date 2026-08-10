import random, uuid
random.seed(707)
rows=[]
for i in range(1500):
 rows.append({'r':[100,85,75,65,40,0][i%6],'f':1 if i%17==0 else 0,'q':round(random.uniform(20,100),3),'v':f"2026-{1+(i%8):02d}-{1+(i%27):02d} 00:00:00",'p':str(uuid.UUID(int=i+1,version=4))})
# Descending r/f/q/v and ascending public ID. ISO datetime lexicographic order is chronological.
rows=sorted(rows,key=lambda r:(-r['r'],-r['f'],-r['q'],tuple(-ord(c) for c in r['v']),r['p']))
seen=[]; cursor=None
while True:
 page=[]
 for r in rows:
  after = cursor is None or (r['r']<cursor['r'] or (r['r']==cursor['r'] and r['f']<cursor['f']) or (r['r']==cursor['r'] and r['f']==cursor['f'] and r['q']<cursor['q']) or (r['r']==cursor['r'] and r['f']==cursor['f'] and r['q']==cursor['q'] and r['v']<cursor['v']) or (r['r']==cursor['r'] and r['f']==cursor['f'] and r['q']==cursor['q'] and r['v']==cursor['v'] and r['p']>cursor['p']))
  if after:
   page.append(r)
   if len(page)==37: break
 if not page: break
 seen.extend(x['p'] for x in page); cursor=page[-1]
assert len(seen)==len(rows), (len(seen),len(rows))
assert len(seen)==len(set(seen))
print(f'PASS: relevance-aware stable cursor traversed {len(seen)} doctors without duplicates or omissions')
