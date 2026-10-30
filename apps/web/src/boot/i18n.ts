import { defineBoot } from '#q-app';
import { Lang } from 'quasar';
import langPtBR from 'quasar/lang/pt-BR';

export default defineBoot(() => {
  Lang.set(langPtBR);
});
