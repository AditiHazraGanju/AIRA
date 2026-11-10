import path from 'path';
import * as sass from 'sass';
import rtlcss from 'rtlcss';
import { writeFileSync, existsSync, mkdirSync } from 'fs';

const rtlcssPlugin = () => {
  return {
    name: 'rtl-css',
    enforce: 'post', // Run after Vite build plugins
    async generateBundle() {

      try {

        // Define paths
        const scssFilePath = path.resolve(__dirname, './resources/sass/app.scss');
        const outputDir = path.resolve(__dirname, './resources/rtl-css');
        const outputFilePath = path.join(outputDir, 'app-rtl.css');

        // Ensure the output directory exists
        if (!existsSync(outputDir)) {
          mkdirSync(outputDir, { recursive: true });
          writeFileSync(path.join(outputDir, 'custom-rtl.css'), `/* Write your custom RTL css here */`);
        }

        // Compile SCSS to CSS
        try {

          const result = sass.compile(scssFilePath, {
            loadPaths: ['node_modules'],
            // style: 'compressed', // Minify CSS output
            silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import'],
          });
  
          // Convert CSS to RTL CSS
          try {

            const rtlResult = rtlcss.process(result.css);
            // Write RTL CSS to file
            writeFileSync(outputFilePath, rtlResult, 'utf-8');
            console.log(`\nRTL CSS generated successfully`);

          } catch (error) {
            console.error('Error generating RTL CSS:', error);
          }

        } catch (error) {
          console.error('Error compiling SCSS:', error);
        }

      } catch (error) {
        console.error('Error generating RTL CSS:', error);
      }
      
    },
  };
};

export { rtlcssPlugin };