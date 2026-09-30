// Copy imagegen outputs unchanged, recording provenance and byte-level verification.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const input=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const referenceManifest=JSON.parse(fs.readFileSync(path.join(root,'var/vtuber-references/manifest.json'),'utf8'));
const promptPath=path.join(root,'docs/content/vtuber-art-prompts.json');
const manifestPath=path.join(root,'docs/content/vtuber-art-manifest.json');
const prompts=fs.existsSync(promptPath)?JSON.parse(fs.readFileSync(promptPath,'utf8')):[];
const manifest=fs.existsSync(manifestPath)?JSON.parse(fs.readFileSync(manifestPath,'utf8')):[];
const firstUrls={vt_0001:'https://www.sanipak.co.jp/dcms_media/image/kizunaai_mainvisual.png',vt_0002:'https://www.sonymusic.co.jp/adm_image/common/artist_image/70009000/70009300/artist_photo/53495.jpg',vt_0003:'https://akari-mir.ai/RP6xlm7h/wp-content/themes/mirai-akari/assets/index/img/akari.png'};
for(const r of input){
 if(!/^vt_00(?:0[1-9]|[12][0-9]|3[0-6])$/.test(r.id))throw Error('Unexpected art ID');
 const data=fs.readFileSync(r.original);
 if(data.subarray(0,8).toString('hex')!=='89504e470d0a1a0a')throw Error('Expected original PNG');
 const target='public/assets/characters/'+r.id+'.png';
 fs.copyFileSync(r.original,path.join(root,target));
 const refs=r.references.map(file=>{const local=path.relative(root,file).split(path.sep).join('/');const source=referenceManifest.find(x=>x.file===local);const url=source?.url||firstUrls[r.id];if(!url)throw Error('Missing public reference URL: '+file);return {url,referenceSha256:crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex')};});
 const prompt={id:r.id,name:r.name,tool:'image_gen.imagegen',generatedAt:'2026-09-30',prompt:r.prompt,transparentBackground:false,referenceImages:refs,originalFile:path.basename(r.original),note:'参考仅用于虚拟角色辨识；新场景、新动作、新构图。最终 PNG 为生成输出的逐字节副本。'};
 const entry={id:r.id,file:target,width:data.readUInt32BE(16),height:data.readUInt32BE(20),bytes:data.length,sha256:crypto.createHash('sha256').update(data).digest('hex'),originalFile:path.basename(r.original)};
 for(const [list,value] of [[prompts,prompt],[manifest,entry]]){const index=list.findIndex(x=>x.id===r.id);if(index<0)list.push(value);else list[index]=value;}
}
for(const [file,list] of [[promptPath,prompts],[manifestPath,manifest]])fs.writeFileSync(file,JSON.stringify(list.sort((a,b)=>a.id.localeCompare(b.id)),null,2)+'\n');
console.log('Imported '+input.length+' original PNGs; '+manifest.length+' total.');
